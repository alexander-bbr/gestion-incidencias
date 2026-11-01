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
                    i.codigo, e.color AS incident_status_color
             FROM notificacion n
             LEFT JOIN incidencia i ON i.id_incidencia = n.id_incidencia
             LEFT JOIN estado e ON e.id_estado = i.id_estado
             WHERE n.id_usuario = :user
             ORDER BY n.fecha DESC, n.id_notificacion DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user' => $userId]);
        $notifications = $statement->fetchAll();
        $statusColors = $this->statusColors();

        foreach ($notifications as &$notification) {
            $notification['accent_color'] = $this->safeColor($notification['incident_status_color'] ?? null);
            $notification['status_from'] = null;
            $notification['status_to'] = null;

            if ($this->splitStatusMessage($notification, $statusColors)) {
                $notification['accent_color'] = $statusColors[$notification['status_to']];
            }
        }
        unset($notification);

        return $notifications;
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

    private function statusColors(): array
    {
        $colors = [];
        foreach ($this->db->query('SELECT nombre, color FROM estado')->fetchAll() as $status) {
            $label = str_replace('_', ' ', $status['nombre']);
            $colors[$label] = $this->safeColor($status['color'] ?? null);
        }
        return $colors;
    }

    private function splitStatusMessage(array &$notification, array $statusColors): bool
    {
        if (!$statusColors) {
            return false;
        }
        $labels = array_keys($statusColors);
        usort($labels, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));
        $pattern = implode('|', array_map(
            static fn(string $label): string => preg_quote($label, '/'),
            $labels
        ));
        $statusPattern = '/^(.* de )(' . $pattern . ')( a )(' . $pattern . ')(\\.)$/u';
        if (!preg_match($statusPattern, $notification['mensaje'], $matches)) {
            return false;
        }

        $notification['message_prefix'] = $matches[1];
        $notification['message_between_statuses'] = $matches[3];
        $notification['message_suffix'] = $matches[5];
        $notification['status_from'] = $matches[2];
        $notification['status_to'] = $matches[4];
        $notification['status_from_color'] = $statusColors[$matches[2]];
        $notification['status_to_color'] = $statusColors[$matches[4]];
        return true;
    }

    private function safeColor(?string $color): string
    {
        return $color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? $color
            : '#315f91';
    }
}
