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
            return null;
        }

        $update = $this->db->prepare('UPDATE usuario SET ultimo_acceso = NOW() WHERE id_usuario = :id');
        $update->execute(['id' => $user['id_usuario']]);
        unset($user['password_hash']);
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
        $parameters = [
            'nombre' => $values['nombre'],
            'apellido' => $values['apellido'],
            'email' => $values['email'],
            'telefono' => $values['telefono'] ?: null,
            'id_rol' => $values['id_rol'],
            'id_departamento' => $values['id_departamento'] ?: null,
        ];
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
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.id_rol,
                    u.id_departamento, u.activo, r.nombre AS role
             FROM usuario u JOIN rol r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function setActive(int $id, bool $active): void
    {
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
