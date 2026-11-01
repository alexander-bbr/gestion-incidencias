<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Incident
{
    public function __construct(private PDO $db)
    {
    }

    public function catalogs(): array
    {
        return [
            'categories' => $this->db->query(
                'SELECT id_categoria, nombre FROM categoria WHERE activo = 1 ORDER BY nombre'
            )->fetchAll(),
            'priorities' => $this->db->query(
                'SELECT id_prioridad, nombre, color FROM prioridad ORDER BY nivel'
            )->fetchAll(),
            'statuses' => $this->db->query(
                'SELECT id_estado, nombre, color, es_final FROM estado ORDER BY id_estado'
            )->fetchAll(),
        ];
    }

    public function page(array $user, array $filters, int $page): array
    {
        [$where, $parameters] = $this->conditions($user, $filters);
        $count = $this->db->prepare(
            'SELECT COUNT(*) FROM incidencia i
             JOIN categoria c ON c.id_categoria = i.id_categoria
             JOIN prioridad p ON p.id_prioridad = i.id_prioridad
             JOIN estado s ON s.id_estado = i.id_estado ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = 15;
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pageCount);

        $statement = $this->db->prepare(
            'SELECT i.id_incidencia, i.codigo, i.titulo, i.fecha_creacion, i.id_reportante,
                    i.id_tecnico, s.nombre AS status, s.color AS status_color,
                    p.nombre AS priority, p.color AS priority_color, c.nombre AS category,
                    CONCAT(reporter.nombre, " ", reporter.apellido) AS reporter,
                    CONCAT(technician.nombre, " ", technician.apellido) AS technician
             FROM incidencia i
             JOIN categoria c ON c.id_categoria = i.id_categoria
             JOIN prioridad p ON p.id_prioridad = i.id_prioridad
             JOIN estado s ON s.id_estado = i.id_estado
             JOIN usuario reporter ON reporter.id_usuario = i.id_reportante
             LEFT JOIN usuario technician ON technician.id_usuario = i.id_tecnico
             ' . $where . '
             ORDER BY i.fecha_creacion DESC, i.id_incidencia DESC
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);
        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pageCount,
            'first' => $total === 0 ? 0 : (($page - 1) * $perPage + 1),
            'last' => min($page * $perPage, $total),
        ];
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT i.*, s.nombre AS status, p.nombre AS priority, c.nombre AS category,
                    CONCAT(reporter.nombre, " ", reporter.apellido) AS reporter,
                    CONCAT(technician.nombre, " ", technician.apellido) AS technician
             FROM incidencia i
             JOIN estado s ON s.id_estado = i.id_estado
             JOIN prioridad p ON p.id_prioridad = i.id_prioridad
             JOIN categoria c ON c.id_categoria = i.id_categoria
             JOIN usuario reporter ON reporter.id_usuario = i.id_reportante
             LEFT JOIN usuario technician ON technician.id_usuario = i.id_tecnico
             WHERE i.id_incidencia = :id'
        );
        $statement->execute(['id' => $id]);
        $item = $statement->fetch();
        return $item ?: null;
    }

    public function attachments(int $id): array
    {
        $statement = $this->db->prepare(
            'SELECT id_adjunto, nombre_archivo, tipo_mime, tamano_bytes
             FROM adjunto WHERE id_incidencia = :id ORDER BY fecha_subida'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    }

    public function attachment(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM adjunto WHERE id_adjunto = :id');
        $statement->execute(['id' => $id]);
        $attachment = $statement->fetch();
        return $attachment ?: null;
    }

    public function create(array $values, int $userId, array $attachments): int
    {
        $this->db->beginTransaction();
        try {
            $priority = $this->db->prepare(
                'SELECT horas_sla FROM prioridad WHERE id_prioridad = :id'
            );
            $priority->execute(['id' => $values['id_prioridad']]);
            $hours = $priority->fetchColumn();
            if ($hours === false) {
                throw new \DomainException('Selecciona una prioridad válida.');
            }

            $insert = $this->db->prepare(
                'INSERT INTO incidencia
                 (codigo, titulo, descripcion, id_categoria, id_prioridad, id_estado,
                  id_reportante, id_tecnico, fecha_asignacion, fecha_limite_sla)
                 VALUES (:codigo, :titulo, :descripcion, :category, :priority, :status,
                         :reporter, :technician, :assignment_date, DATE_ADD(NOW(), INTERVAL :hours HOUR))'
            );
            $insert->bindValue(':codigo', 'INC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))));
            $insert->bindValue(':titulo', $values['titulo']);
            $insert->bindValue(':descripcion', $values['descripcion']);
            $insert->bindValue(':category', $values['id_categoria'], PDO::PARAM_INT);
            $insert->bindValue(':priority', $values['id_prioridad'], PDO::PARAM_INT);
            $insert->bindValue(':status', $this->statusId('ABIERTA'), PDO::PARAM_INT);
            $insert->bindValue(':reporter', $userId, PDO::PARAM_INT);
            $insert->bindValue(':technician', $values['id_tecnico'] ?: null, $values['id_tecnico'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $insert->bindValue(':assignment_date', $values['id_tecnico'] ? date('Y-m-d H:i:s') : null, $values['id_tecnico'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insert->bindValue(':hours', (int) $hours, PDO::PARAM_INT);
            $insert->execute();
            $id = (int) $this->db->lastInsertId();

            foreach ($attachments as $attachment) {
                $this->insertAttachment($id, $userId, $attachment);
            }
            $this->history($id, $userId, 'CREACION', null, 'ABIERTA');
            if ($values['id_tecnico']) {
                $this->history($id, $userId, 'ASIGNACION', null, (string) $values['id_tecnico']);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateForReporter(int $id, int $reporterId, array $values): bool
    {
        $this->db->beginTransaction();
        try {
            $current = $this->db->prepare(
                'SELECT id_tecnico FROM incidencia
                 WHERE id_incidencia = :id AND id_reportante = :reporter FOR UPDATE'
            );
            $current->execute(['id' => $id, 'reporter' => $reporterId]);
            $oldTechnician = $current->fetchColumn();
            if ($oldTechnician === false) {
                $this->db->rollBack();
                return false;
            }

            $technicianId = $values['id_tecnico'] !== '' ? (int) $values['id_tecnico'] : null;
            $update = $this->db->prepare(
                'UPDATE incidencia
                 SET titulo = :title, descripcion = :description,
                     id_categoria = :category, id_prioridad = :priority,
                     id_tecnico = :technician,
                     fecha_asignacion = CASE
                         WHEN :assignment_removed = 1 THEN NULL
                         WHEN :assignment_changed = 1 AND :has_technician = 1 THEN NOW()
                         ELSE fecha_asignacion
                     END
                 WHERE id_incidencia = :id AND id_reportante = :reporter'
            );
            $update->execute([
                'title' => $values['titulo'],
                'description' => $values['descripcion'],
                'category' => $values['id_categoria'],
                'priority' => $values['id_prioridad'],
                'technician' => $technicianId,
                'assignment_removed' => (int) $oldTechnician !== (int) ($technicianId ?? 0)
                    && $technicianId === null ? 1 : 0,
                'assignment_changed' => (int) $oldTechnician !== (int) ($technicianId ?? 0) ? 1 : 0,
                'has_technician' => $technicianId !== null ? 1 : 0,
                'id' => $id,
                'reporter' => $reporterId,
            ]);

            if ((int) $oldTechnician !== (int) ($technicianId ?? 0)) {
                $this->history(
                    $id,
                    $reporterId,
                    'ASIGNACION',
                    $oldTechnician !== null ? (string) $oldTechnician : null,
                    $technicianId !== null ? (string) $technicianId : null
                );
            }
            $this->history($id, $reporterId, 'EDICION_REPORTANTE', null, 'Datos de incidencia actualizados');
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateForAdministrator(int $id, array $values, int $actorId): void
    {
        $old = $this->find($id);
        if ($old === null) {
            throw new \DomainException('La incidencia ya no existe.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'UPDATE incidencia SET titulo = :titulo, descripcion = :description,
                 id_categoria = :category, id_prioridad = :priority, id_estado = :status,
                 id_tecnico = :technician,
                 fecha_asignacion = CASE WHEN :assignment_changed = 1 AND :assignment_has_technician = 1
                                         THEN NOW() ELSE fecha_asignacion END,
                 solucion = :solution,
                 fecha_resolucion = CASE WHEN :resolved_set = 1 AND fecha_resolucion IS NULL
                                         THEN NOW() ELSE fecha_resolucion END,
                 fecha_cierre = CASE WHEN :closed_set = 1 AND fecha_cierre IS NULL
                                     THEN NOW() WHEN :closed_clear = 1 THEN NULL ELSE fecha_cierre END
                 WHERE id_incidencia = :id'
            );
            $statement->execute([
                'titulo' => $values['titulo'],
                'description' => $values['descripcion'],
                'category' => $values['id_categoria'],
                'priority' => $values['id_prioridad'],
                'status' => $values['id_estado'],
                'technician' => $values['id_tecnico'] ?: null,
                'assignment_changed' => (int) $old['id_tecnico'] !== (int) ($values['id_tecnico'] ?: 0) ? 1 : 0,
                'assignment_has_technician' => $values['id_tecnico'] ? 1 : 0,
                'solution' => $values['solucion'] ?: null,
                'resolved_set' => $values['status_name'] === 'RESUELTA' ? 1 : 0,
                'closed_set' => in_array($values['status_name'], ['CERRADA', 'RECHAZADA'], true) ? 1 : 0,
                'closed_clear' => !in_array($values['status_name'], ['CERRADA', 'RECHAZADA'], true) ? 1 : 0,
                'id' => $id,
            ]);
            if ($old['status'] !== $values['status_name']) {
                $this->history($id, $actorId, 'CAMBIO_ESTADO', $old['status'], $values['status_name']);
            }
            if ((int) $old['id_tecnico'] !== (int) ($values['id_tecnico'] ?: 0)) {
                $this->history($id, $actorId, 'ASIGNACION', $old['id_tecnico'] ? (string) $old['id_tecnico'] : null,
                    $values['id_tecnico'] ?: null);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateForTechnician(int $id, int $technicianId, int $statusId, string $statusName, string $solution): bool
    {
        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare(
                'SELECT s.nombre FROM incidencia i JOIN estado s ON s.id_estado = i.id_estado
                 WHERE i.id_incidencia = :id AND i.id_tecnico = :technician'
            );
            $existing->execute(['id' => $id, 'technician' => $technicianId]);
            $oldStatus = $existing->fetchColumn();
            if ($oldStatus === false) {
                $this->db->rollBack();
                return false;
            }
            $statement = $this->db->prepare(
                'UPDATE incidencia SET id_estado = :status, solucion = :solution,
                 fecha_resolucion = CASE WHEN :resolved_set = 1 AND fecha_resolucion IS NULL
                                         THEN NOW() ELSE fecha_resolucion END
                 WHERE id_incidencia = :id AND id_tecnico = :technician'
            );
            $statement->execute([
                'status' => $statusId,
                'solution' => $solution !== '' ? $solution : null,
                'resolved_set' => $statusName === 'RESUELTA' ? 1 : 0,
                'id' => $id,
                'technician' => $technicianId,
            ]);
            if ($oldStatus !== $statusName) {
                $this->history($id, $technicianId, 'CAMBIO_ESTADO', $oldStatus, $statusName);
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->db->prepare('DELETE FROM incidencia WHERE id_incidencia = :id');
        $statement->execute(['id' => $id]);
    }

    public function homeCounts(array $user): array
    {
        [$where, $parameters] = $this->conditions($user, []);
        $statement = $this->db->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(s.nombre = "ABIERTA"), 0) AS open_count,
                    COALESCE(SUM(s.nombre = "EN_PROGRESO"), 0) AS progress_count,
                    COALESCE(SUM(s.nombre = "RESUELTA"), 0) AS resolved_count
             FROM incidencia i JOIN estado s ON s.id_estado = i.id_estado ' . $where
        );
        $statement->execute($parameters);
        return $statement->fetch() ?: ['total' => 0, 'open_count' => 0, 'progress_count' => 0, 'resolved_count' => 0];
    }

    private function conditions(array $user, array $filters): array
    {
        $parts = [];
        $parameters = [];
        if ($user['role'] === User::ROLE_USER) {
            $parts[] = 'i.id_reportante = :reporter';
            $parameters['reporter'] = $user['id_usuario'];
        } elseif ($user['role'] === User::ROLE_TECHNICIAN) {
            $parts[] = 'i.id_tecnico = :technician';
            $parameters['technician'] = $user['id_usuario'];
        } elseif ($user['role'] !== User::ROLE_ADMIN) {
            $parts[] = '1 = 0';
        }
        foreach ([
            'status' => ['i.id_estado', 'status'],
            'priority' => ['i.id_prioridad', 'priority'],
            'category' => ['i.id_categoria', 'category'],
        ] as $key => [$column, $parameter]) {
            if (isset($filters[$key]) && ctype_digit((string) $filters[$key])) {
                $parts[] = $column . ' = :' . $parameter;
                $parameters[$parameter] = (int) $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            $parts[] = 'i.titulo LIKE :title';
            $parameters['title'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['from']) && $this->validDate($filters['from'])) {
            $parts[] = 'i.fecha_creacion >= :date_from';
            $parameters['date_from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to']) && $this->validDate($filters['to'])) {
            $parts[] = 'i.fecha_creacion < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $parameters['date_to'] = $filters['to'];
        }
        return [$parts ? 'WHERE ' . implode(' AND ', $parts) : '', $parameters];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function statusId(string $name): int
    {
        $statement = $this->db->prepare('SELECT id_estado FROM estado WHERE nombre = :name');
        $statement->execute(['name' => $name]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('No se encontró el estado ABIERTA en la base de datos.');
        }
        return (int) $id;
    }

    private function insertAttachment(int $incidentId, int $userId, array $attachment): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO adjunto
             (id_incidencia, nombre_archivo, ruta, tipo_mime, tamano_bytes, id_usuario)
             VALUES (:incident, :name, :path, :mime, :size, :user)'
        );
        $statement->execute([
            'incident' => $incidentId,
            'name' => $attachment['name'],
            'path' => $attachment['path'],
            'mime' => $attachment['mime'],
            'size' => $attachment['size'],
            'user' => $userId,
        ]);
    }

    private function history(int $incidentId, int $userId, string $action, ?string $before, ?string $after): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO historial_incidencia (id_incidencia, id_usuario, accion, valor_anterior, valor_nuevo)
             VALUES (:incident, :user, :action, :before, :after)'
        );
        $statement->execute([
            'incident' => $incidentId,
            'user' => $userId,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
