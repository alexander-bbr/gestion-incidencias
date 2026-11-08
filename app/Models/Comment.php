<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Comment
{
    public function __construct(private PDO $db)
    {
    }

    public function forIncident(int $incidentId, bool $includeInternal): array
    {
        $visibility = $includeInternal ? '' : ' AND c.es_interno = 0';
        $statement = $this->db->prepare(
            'SELECT c.id_comentario, c.id_usuario, c.texto, c.es_interno, c.fecha,
                    u.nombre, u.apellido, r.nombre AS role
             FROM comentario c
             JOIN usuario u ON u.id_usuario = c.id_usuario
             JOIN rol r ON r.id_rol = u.id_rol
             WHERE c.id_incidencia = :incident' . $visibility . '
             ORDER BY c.fecha ASC, c.id_comentario ASC'
        );
        $statement->execute(['incident' => $incidentId]);
        return $statement->fetchAll();
    }

    public function create(int $incidentId, int $userId, string $text, bool $internal, string $incidentCode): void
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO comentario (id_incidencia, id_usuario, texto, es_interno)
                 VALUES (:incident, :user, :text, :internal)'
            );
            $statement->execute([
                'incident' => $incidentId,
                'user' => $userId,
                'text' => $text,
                'internal' => $internal ? 1 : 0,
            ]);
            (new Audit($this->db))->record(
                $userId,
                $internal ? 'COMMENT_INTERNAL' : 'COMMENT_PUBLIC',
                'incidencia',
                (string) $incidentId,
                $incidentCode,
                null,
                ['visibilidad' => $internal ? 'Nota interna' : 'Público']
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
