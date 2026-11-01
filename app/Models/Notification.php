<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Notification
{
    public function __construct(private PDO $db)
    {
    }

    public function recentForUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $statement = $this->db->prepare(
            'SELECT n.id_notificacion, n.id_incidencia, n.mensaje, n.leida, n.fecha,
                    i.codigo
             FROM notificacion n
             LEFT JOIN incidencia i ON i.id_incidencia = n.id_incidencia
             WHERE n.id_usuario = :user
             ORDER BY n.fecha DESC, n.id_notificacion DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM notificacion WHERE id_usuario = :user AND leida = 0'
        );
        $statement->execute(['user' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function markRead(int $notificationId, int $userId): ?int
    {
        $statement = $this->db->prepare(
            'SELECT id_incidencia FROM notificacion WHERE id_notificacion = :notification AND id_usuario = :user'
        );
        $statement->execute(['notification' => $notificationId, 'user' => $userId]);
        $incidentId = $statement->fetchColumn();
        if ($incidentId === false) {
            return null;
        }

        $update = $this->db->prepare(
            'UPDATE notificacion SET leida = 1
             WHERE id_notificacion = :notification AND id_usuario = :user'
        );
        $update->execute(['notification' => $notificationId, 'user' => $userId]);
        return $incidentId !== null ? (int) $incidentId : null;
    }

    public function markAllRead(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE notificacion SET leida = 1 WHERE id_usuario = :user AND leida = 0'
        );
        $statement->execute(['user' => $userId]);
    }
}
