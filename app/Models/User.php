<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public const ROLE_USER = 'USUARIO';
    public const ROLE_TECHNICIAN = 'TECNICO';
    public const ROLE_ADMIN = 'ADMINISTRADOR';

    public function __construct(private PDO $db)
    {
    }

    public function hasAdministrator(): bool
    {
        return (bool) $this->db->query(
            "SELECT EXISTS(SELECT 1 FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
             WHERE r.nombre = 'ADMINISTRADOR')"
        )->fetchColumn();
    }

    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.password_hash,
                    u.id_departamento, r.nombre AS role
             FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
             WHERE u.email = :email AND u.activo = 1 LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            (new Audit($this->db))->record(
                null,
                'LOGIN_FAILED',
                'autenticacion',
                null,
                'Inicio de sesión'
            );
            return null;
        }

        $update = $this->db->prepare('UPDATE usuario SET ultimo_acceso = NOW() WHERE id_usuario = :id');
        $update->execute(['id' => $user['id_usuario']]);
        unset($user['password_hash']);
        (new Audit($this->db))->record(
            (int) $user['id_usuario'],
            'LOGIN_SUCCESS',
            'autenticacion',
            (string) $user['id_usuario'],
            'Inicio de sesión'
        );
        return $user;
    }

    public function roles(): array
    {
        return $this->db->query('SELECT id_rol, nombre FROM rol ORDER BY id_rol')->fetchAll();
    }

    public function departments(): array
    {
        return $this->db->query(
            'SELECT id_departamento, nombre FROM departamento WHERE activo = 1 ORDER BY nombre'
        )->fetchAll();
    }

    public function technicians(): array
    {
        $statement = $this->db->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido
             FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
             WHERE r.nombre = :role AND u.activo = 1 ORDER BY u.apellido, u.nombre'
        );
        $statement->execute(['role' => self::ROLE_TECHNICIAN]);
        return $statement->fetchAll();
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.activo,
                    u.id_rol, u.id_departamento, r.nombre AS role, d.nombre AS department
             FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
             LEFT JOIN departamento d ON d.id_departamento = u.id_departamento
             ORDER BY u.id_usuario DESC'
        )->fetchAll();
    }

    public function createAdministrator(array $values): void
    {
        $locked = (int) $this->db->query(
            "SELECT GET_LOCK('gestion_incidencias_admin_setup', 10)"
        )->fetchColumn();
        if ($locked !== 1) {
            throw new \RuntimeException('No se pudo reservar el proceso de configuración inicial.');
        }
        try {
            $this->db->beginTransaction();
            try {
                if ($this->hasAdministrator()) {
                    throw new \DomainException('Ya existe una cuenta de administrador.');
                }
                $roleId = $this->roleId(self::ROLE_ADMIN);
                $statement = $this->db->prepare(
                    'INSERT INTO usuario (nombre, apellido, email, password_hash, id_rol)
                     VALUES (:nombre, :apellido, :email, :password_hash, :id_rol)'
                );
                $statement->execute([
                    'nombre' => $values['nombre'],
                    'apellido' => $values['apellido'],
                    'email' => $values['email'],
                    'password_hash' => password_hash($values['password'], PASSWORD_DEFAULT),
                    'id_rol' => $roleId,
                ]);
                $userId = (int) $this->db->lastInsertId();
                (new Audit($this->db))->record(
                    $userId,
                    'USER_CREATED',
                    'usuario',
                    (string) $userId,
                    $values['nombre'] . ' ' . $values['apellido'],
                    null,
                    ['Rol' => self::ROLE_ADMIN]
                );
                $this->db->commit();
            } catch (\Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('gestion_incidencias_admin_setup')");
        }
    }

    public function save(array $values, ?int $id = null): void
    {
        $before = $id === null ? null : $this->find($id);
        if ($id !== null && $before === null) {
            throw new \DomainException('El usuario ya no existe.');
        }
        $parameters = [
            'nombre' => $values['nombre'],
            'apellido' => $values['apellido'],
            'email' => $values['email'],
            'telefono' => $values['telefono'] ?: null,
            'id_rol' => $values['id_rol'],
            'id_departamento' => $values['id_departamento'] ?: null,
        ];
        $this->db->beginTransaction();
        try {
            if ($id === null) {
                $parameters['password_hash'] = password_hash($values['password'], PASSWORD_DEFAULT);
                $statement = $this->db->prepare(
                    'INSERT INTO usuario (nombre, apellido, email, telefono, id_rol, id_departamento, password_hash)
                     VALUES (:nombre, :apellido, :email, :telefono, :id_rol, :id_departamento, :password_hash)'
                );
            } else {
                $parameters['id'] = $id;
                if ($values['password'] !== '') {
                    $parameters['password_hash'] = password_hash($values['password'], PASSWORD_DEFAULT);
                    $statement = $this->db->prepare(
                        'UPDATE usuario SET nombre = :nombre, apellido = :apellido, email = :email,
                         telefono = :telefono, id_rol = :id_rol, id_departamento = :id_departamento,
                         password_hash = :password_hash WHERE id_usuario = :id'
                    );
                } else {
                    $statement = $this->db->prepare(
                        'UPDATE usuario SET nombre = :nombre, apellido = :apellido, email = :email,
                         telefono = :telefono, id_rol = :id_rol, id_departamento = :id_departamento
                         WHERE id_usuario = :id'
                    );
                }
            }
            $statement->execute($parameters);
            $userId = $id ?? (int) $this->db->lastInsertId();
            $after = $this->find($userId);
            if ($after === null) {
                throw new \RuntimeException('No se pudo recuperar el usuario guardado para auditoría.');
            }
            $beforeValues = $before === null ? null : $this->auditSnapshot($before);
            $afterValues = $this->auditSnapshot($after);
            $sensitiveChanges = [];
            if ($before === null || $before['email'] !== $values['email']) {
                $sensitiveChanges[] = 'Correo electrónico (valor omitido)';
            }
            if ($before === null || $before['telefono'] !== ($values['telefono'] ?: null)) {
                if (($values['telefono'] ?? '') !== '' || ($before['telefono'] ?? null) !== null) {
                    $sensitiveChanges[] = 'Teléfono (valor omitido)';
                }
            }
            if ($values['password'] !== '') {
                $sensitiveChanges[] = 'Contraseña (valor omitido)';
            }
            if ($sensitiveChanges) {
                $afterValues['Campos sensibles modificados (valores omitidos)'] = $sensitiveChanges;
                if ($beforeValues !== null) {
                    $beforeValues['Campos sensibles modificados (valores omitidos)'] = $sensitiveChanges;
                }
            }
            $actor = current_user();
            (new Audit($this->db))->record(
                $actor !== null ? (int) $actor['id_usuario'] : null,
                $id === null ? 'USER_CREATED' : 'USER_UPDATED',
                'usuario',
                (string) $userId,
                $after['nombre'] . ' ' . $after['apellido'],
                $beforeValues,
                $afterValues
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.id_rol,
                    u.id_departamento, u.activo, r.nombre AS role, d.nombre AS department
             FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
             LEFT JOIN departamento d ON d.id_departamento = u.id_departamento
             WHERE u.id_usuario = :id'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function setActive(int $id, bool $active): void
    {
        $before = $this->find($id);
        if ($before === null || (bool) $before['activo'] === $active) {
            return;
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "UPDATE usuario SET activo = :active WHERE id_usuario = :id
                 AND id_usuario <> :current_id
                 AND id_rol <> (SELECT id_rol FROM rol WHERE nombre = 'ADMINISTRADOR')"
            );
            $statement->execute([
                'active' => $active ? 1 : 0,
                'id' => $id,
                'current_id' => current_user()['id_usuario'],
            ]);
            if ($statement->rowCount() > 0) {
                $after = $this->find($id);
                if ($after !== null) {
                    (new Audit($this->db))->record(
                        (int) current_user()['id_usuario'],
                        $active ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
                        'usuario',
                        (string) $id,
                        $after['nombre'] . ' ' . $after['apellido'],
                        $this->auditSnapshot($before),
                        $this->auditSnapshot($after)
                    );
                }
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function auditSnapshot(array $user): array
    {
        return [
            'Nombre' => $user['nombre'] . ' ' . $user['apellido'],
            'Rol' => $user['role'],
            'Departamento' => $user['department'] ?: 'Sin departamento',
            'Estado de cuenta' => (bool) $user['activo'] ? 'Activo' : 'Inactivo',
        ];
    }

    private function roleId(string $name): int
    {
        $statement = $this->db->prepare('SELECT id_rol FROM rol WHERE nombre = :name');
        $statement->execute(['name' => $name]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('No se encontró el rol de administrador en la base de datos.');
        }
        return (int) $id;
    }
}
