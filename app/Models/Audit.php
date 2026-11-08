<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Audit
{
    private const PAGE_SIZE = 50;

    public function __construct(private PDO $db)
    {
    }

    public function record(
        ?int $actorId,
        string $action,
        string $entityType,
        ?string $entityId,
        string $entityLabel,
        ?array $before = null,
        ?array $after = null
    ): void {
        $actor = null;
        if ($actorId !== null) {
            $statement = $this->db->prepare(
                'SELECT CONCAT(u.nombre, " ", u.apellido) AS name, r.nombre AS role
                 FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
                 WHERE u.id_usuario = :id'
            );
            $statement->execute(['id' => $actorId]);
            $actor = $statement->fetch() ?: null;
        }

        $statement = $this->db->prepare(
            'INSERT INTO auditoria
             (actor_id, actor_name, actor_role, accion, entidad_tipo, entidad_id, entidad_nombre,
              valores_anteriores, valores_nuevos)
             VALUES (:actor_id, :actor_name, :actor_role, :action, :entity_type, :entity_id,
                     :entity_name, :before_values, :after_values)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'actor_name' => $actor['name'] ?? ($actorId === null ? 'No autenticado' : 'Usuario #' . $actorId),
            'actor_role' => $actor['role'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityLabel,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function page(int $page): array
    {
        $total = (int) $this->db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $statement = $this->db->query(
            'SELECT id_auditoria, actor_name, actor_role, accion, entidad_tipo, entidad_id,
                    entidad_nombre, valores_anteriores, valores_nuevos, fecha
             FROM auditoria
             ORDER BY fecha DESC, id_auditoria DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            $item['valores_anteriores'] = $this->decodeValues($item['valores_anteriores']);
            $item['valores_nuevos'] = $this->decodeValues($item['valores_nuevos']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'first' => $total === 0 ? 0 : $offset + 1,
            'last' => min($offset + self::PAGE_SIZE, $total),
        ];
    }

    private function decodeValues(?string $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $decoded = json_decode($values, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }
}
