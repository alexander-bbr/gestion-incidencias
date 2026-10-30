<section class="panel">
    <div class="panel-heading"><div><h2>Usuarios del sistema</h2><p class="muted">Administra accesos, roles y restablecimiento de contraseñas.</p></div><a class="button button-primary" href="<?= e(url('user-edit')) ?>">＋ Nuevo Usuario</a></div>
    <?php if (!$users): ?><div class="empty-state">No hay usuarios registrados.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($users as $item): ?><tr>
            <td><?= (int) $item['id_usuario'] ?></td><td class="table-title"><?= e($item['nombre'] . ' ' . $item['apellido']) ?></td>
            <td><?= e($item['email']) ?></td><td><?= e(ucfirst(strtolower($item['role']))) ?></td>
            <td><span class="badge <?= $item['activo'] ? 'badge-active' : 'badge-inactive' ?>"><?= $item['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
            <td class="actions"><a class="text-link" href="<?= e(url('user-edit', ['id' => $item['id_usuario']])) ?>">Editar</a>
                <?php if ($item['role'] !== 'ADMINISTRADOR'): ?><form method="post" action="<?= e(url('user-toggle', ['id' => $item['id_usuario']])) ?>" onsubmit="return confirm('¿<?= $item['activo'] ? 'Desactivar' : 'Activar' ?> este usuario?');"><?= csrf_field() ?><input type="hidden" name="active" value="<?= $item['activo'] ? '0' : '1' ?>"><button class="<?= $item['activo'] ? 'link-danger' : 'text-link' ?>" type="submit"><?= $item['activo'] ? 'Desactivar' : 'Activar' ?></button></form><?php endif; ?>
            </td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
