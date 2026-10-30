<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Database;
use App\Models\Incident;
use App\Models\Report;
use App\Models\User;
use DomainException;
use PDOException;
use Throwable;

final class AppController
{
    private \PDO $db;
    private User $users;
    private Incident $incidents;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->users = new User($this->db);
        $this->incidents = new Incident($this->db);
    }

    public function dispatch(): void
    {
        $route = is_string($_GET['route'] ?? null) ? $_GET['route'] : 'home';
        if (!$this->users->hasAdministrator() && $route !== 'setup') {
            redirect('setup');
        }
        if ($this->users->hasAdministrator() && $route === 'setup') {
            redirect(current_user() ? 'home' : 'login');
        }

        switch ($route) {
            case 'setup':
                $this->setup();
                break;
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'password-help':
                render('password-help', ['title' => 'Recuperar acceso', 'public' => true]);
                break;
            case 'home':
                $this->home();
                break;
            case 'incidents':
                $this->incidentList();
                break;
            case 'incident-create':
                $this->incidentCreate();
                break;
            case 'incident-view':
                $this->incidentView();
                break;
            case 'incident-edit':
                $this->incidentEdit();
                break;
            case 'incident-delete':
                $this->incidentDelete();
                break;
            case 'attachment':
                $this->attachment();
                break;
            case 'users':
                $this->userList();
                break;
            case 'user-edit':
                $this->userEdit();
                break;
            case 'user-toggle':
                $this->userToggle();
                break;
            case 'reports':
                $this->reports();
                break;
            case 'reports-export':
                $this->reportsExport();
                break;
            default:
                http_response_code(404);
                render('not-found', ['title' => 'Página no encontrada']);
        }
    }

    private function setup(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $values = [
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'apellido' => trim((string) ($_POST['apellido'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
            ];
            $error = $this->validateUser($values, true);
            if ($error !== null) {
                render('setup', ['title' => 'Crear administrador', 'error' => $error, 'values' => $values, 'public' => true]);
                return;
            }
            try {
                $this->users->createAdministrator($values);
            } catch (DomainException $exception) {
                flash('error', $exception->getMessage());
                redirect('login');
            } catch (PDOException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    render('setup', [
                        'title' => 'Crear administrador',
                        'error' => 'Ese correo ya está registrado en la base de datos.',
                        'values' => $values,
                        'public' => true,
                    ]);
                    return;
                }
                throw $exception;
            }
            flash('success', 'Administrador creado. Ya puedes iniciar sesión.');
            redirect('login');
        }

        render('setup', ['title' => 'Crear administrador', 'values' => [], 'public' => true]);
    }

    private function login(): void
    {
        if (current_user()) {
            redirect('home');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $user = $this->users->authenticate($email, $password);
            if ($user === null) {
                render('login', [
                    'title' => 'Iniciar sesión',
                    'error' => 'Correo o contraseña incorrectos, o cuenta desactivada.',
                    'email' => $email,
                    'public' => true,
                ]);
                return;
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            redirect('home');
        }
        render('login', ['title' => 'Iniciar sesión', 'public' => true]);
    }

    private function logout(): void
    {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login');
    }

    private function home(): void
    {
        $user = require_login();
        $counts = $this->incidents->homeCounts($user);
        $recent = $this->incidents->page($user, [], 1)['items'];
        render('home', ['title' => 'Inicio', 'user' => $user, 'counts' => $counts, 'recent' => array_slice($recent, 0, 5)]);
    }

    private function incidentList(): void
    {
        $user = require_login();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'priority' => (string) ($_GET['priority'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
        ];
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $result = $this->incidents->page($user, $filters, $page !== false ? $page : 1);
        render('incidents/index', array_merge([
            'title' => 'Incidencias',
            'result' => $result,
            'filters' => $filters,
            'catalogs' => $this->incidents->catalogs(),
            'user' => $user,
        ]));
    }

    private function incidentCreate(): void
    {
        $user = require_role(User::ROLE_USER, User::ROLE_ADMIN);
        $catalogs = $this->incidents->catalogs();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $values = $this->incidentValues($_POST);
            $error = $this->validateIncident($values, $catalogs);
            if ($error !== null) {
                render('incidents/form', [
                    'title' => 'Nueva Incidencia',
                    'values' => $values,
                    'catalogs' => $catalogs,
                    'error' => $error,
                    'editing' => false,
                ]);
                return;
            }

            $movedFiles = [];
            try {
                $attachments = $this->storeUploads($movedFiles);
                $id = $this->incidents->create($values, (int) $user['id_usuario'], $attachments);
            } catch (DomainException $exception) {
                $this->removeStoredFiles($movedFiles);
                render('incidents/form', [
                    'title' => 'Nueva Incidencia',
                    'values' => $values,
                    'catalogs' => $catalogs,
                    'error' => $exception->getMessage(),
                    'editing' => false,
                ]);
                return;
            } catch (Throwable $exception) {
                $this->removeStoredFiles($movedFiles);
                throw $exception;
            }
            flash('success', 'La incidencia fue registrada correctamente.');
            redirect('incident-view', ['id' => $id]);
        }

        render('incidents/form', [
            'title' => 'Nueva Incidencia',
            'values' => ['titulo' => '', 'descripcion' => '', 'id_categoria' => '', 'id_prioridad' => ''],
            'catalogs' => $catalogs,
            'editing' => false,
        ]);
    }

    private function incidentView(): void
    {
        $user = require_login();
        $id = $this->routeId();
        $incident = $this->incidents->find($id);
        if (!$incident || !$this->canViewIncident($user, $incident)) {
            http_response_code(404);
            render('not-found', ['title' => 'Incidencia no encontrada']);
            return;
        }
        $technicianCanUpdate = $user['role'] === User::ROLE_TECHNICIAN
            && (int) $incident['id_tecnico'] === (int) $user['id_usuario']
            && !in_array($incident['status'], ['CERRADA', 'RECHAZADA'], true);
        render('incidents/view', [
            'title' => $incident['codigo'],
            'incident' => $incident,
            'attachments' => $this->incidents->attachments($id),
            'catalogs' => $this->incidents->catalogs(),
            'technicians' => is_admin() ? $this->users->technicians() : [],
            'canManage' => is_admin(),
            'technicianCanUpdate' => $technicianCanUpdate,
        ]);
    }

    private function incidentEdit(): void
    {
        $user = require_login();
        $id = $this->routeId();
        $incident = $this->incidents->find($id);
        if (!$incident || !$this->canViewIncident($user, $incident)) {
            http_response_code(404);
            render('not-found', ['title' => 'Incidencia no encontrada']);
            return;
        }

        if (is_admin()) {
            verify_csrf();
            $values = $this->incidentValues($_POST);
            $values['id_tecnico'] = (string) ($_POST['id_tecnico'] ?? '');
            $values['id_estado'] = (string) ($_POST['id_estado'] ?? '');
            $status = $this->statusById($values['id_estado']);
            $technicianIds = array_map('intval', array_column($this->users->technicians(), 'id_usuario'));
            $technicianValid = $values['id_tecnico'] === ''
                || (ctype_digit($values['id_tecnico']) && in_array((int) $values['id_tecnico'], $technicianIds, true));
            if ($status === null || !$technicianValid
                || $this->validateIncident($values, $this->incidents->catalogs()) !== null) {
                flash('error', 'Revisa los datos de la incidencia e inténtalo de nuevo.');
                redirect('incident-view', ['id' => $id]);
            }
            $values['status_name'] = $status['nombre'];
            $this->incidents->updateForAdministrator($id, $values, (int) $user['id_usuario']);
            flash('success', 'Incidencia actualizada.');
        } elseif ($user['role'] === User::ROLE_TECHNICIAN
            && (int) $incident['id_tecnico'] === (int) $user['id_usuario']
            && !in_array($incident['status'], ['CERRADA', 'RECHAZADA'], true)) {
            verify_csrf();
            $statusId = (int) ($_POST['id_estado'] ?? 0);
            $status = $this->statusById((string) $statusId);
            $solution = trim((string) ($_POST['solucion'] ?? ''));
            if ($status === null || !in_array($status['nombre'], ['EN_PROGRESO', 'RESUELTA', 'RECHAZADA'], true)) {
                flash('error', 'Selecciona un estado permitido.');
                redirect('incident-view', ['id' => $id]);
            }
            if ($status['nombre'] === 'RESUELTA' && $solution === '') {
                flash('error', 'Escribe la solución antes de marcar la incidencia como resuelta.');
                redirect('incident-view', ['id' => $id]);
            }
            if (!$this->incidents->updateForTechnician(
                $id,
                (int) $user['id_usuario'],
                $statusId,
                $status['nombre'],
                $solution
            )) {
                flash('error', 'No se pudo actualizar la incidencia asignada.');
                redirect('incidents');
            }
            flash('success', 'La atención de la incidencia fue actualizada.');
        } else {
            http_response_code(403);
            exit('No tienes permiso para modificar esta incidencia.');
        }
        redirect('incident-view', ['id' => $id]);
    }

    private function incidentDelete(): void
    {
        require_role(User::ROLE_ADMIN);
        verify_csrf();
        $id = $this->routeId();
        $attachments = $this->incidents->attachments($id);
        $this->incidents->delete($id);
        foreach ($attachments as $attachment) {
            $path = ROOT_PATH . '/storage/uploads/' . basename((string) $attachment['ruta']);
            if (is_file($path) && !unlink($path)) {
                error_log('No se pudo eliminar el adjunto ' . $path);
            }
        }
        flash('success', 'La incidencia fue eliminada.');
        redirect('incidents');
    }

    private function attachment(): void
    {
        $user = require_login();
        $attachment = $this->incidents->attachment($this->routeId());
        if (!$attachment) {
            http_response_code(404);
            exit('Adjunto no encontrado.');
        }
        $incident = $this->incidents->find((int) $attachment['id_incidencia']);
        if (!$incident || !$this->canViewIncident($user, $incident)) {
            http_response_code(404);
            exit('Adjunto no encontrado.');
        }
        $path = ROOT_PATH . '/storage/uploads/' . basename((string) $attachment['ruta']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('El archivo adjunto ya no está disponible.');
        }
        header('Content-Type: ' . ($attachment['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($attachment['nombre_archivo'])) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function userList(): void
    {
        require_role(User::ROLE_ADMIN);
        render('users/index', ['title' => 'Gestión de Usuarios', 'users' => $this->users->all()]);
    }

    private function userEdit(): void
    {
        $currentUser = require_role(User::ROLE_ADMIN);
        $id = isset($_GET['id']) ? $this->routeId() : null;
        $existing = $id !== null ? $this->users->find($id) : null;
        if ($id !== null && $existing === null) {
            http_response_code(404);
            render('not-found', ['title' => 'Usuario no encontrado']);
            return;
        }
        $values = $existing ?? [
            'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => '',
            'id_rol' => '', 'id_departamento' => '',
        ];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $values = array_merge($values, [
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'apellido' => trim((string) ($_POST['apellido'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'telefono' => trim((string) ($_POST['telefono'] ?? '')),
                'id_rol' => (string) ($_POST['id_rol'] ?? ''),
                'id_departamento' => (string) ($_POST['id_departamento'] ?? ''),
                'password' => (string) ($_POST['password'] ?? ''),
            ]);
            $error = $this->validateUser($values, $id === null);
            if ($error === null && $existing !== null && $id === (int) $currentUser['id_usuario']
                && (int) $values['id_rol'] !== (int) $existing['id_rol']) {
                $error = 'Para conservar el acceso, no puedes cambiar tu propio rol.';
            }
            if ($error !== null) {
                render('users/form', [
                    'title' => $id === null ? 'Nuevo Usuario' : 'Editar usuario',
                    'values' => $values,
                    'roles' => $this->users->roles(),
                    'departments' => $this->users->departments(),
                    'error' => $error,
                    'editing' => $id !== null,
                ]);
                return;
            }
            try {
                $this->users->save($values, $id);
            } catch (PDOException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    $error = 'Ese correo ya está registrado.';
                    render('users/form', [
                        'title' => $id === null ? 'Nuevo Usuario' : 'Editar usuario',
                        'values' => $values,
                        'roles' => $this->users->roles(),
                        'departments' => $this->users->departments(),
                        'error' => $error,
                        'editing' => $id !== null,
                    ]);
                    return;
                }
                throw $exception;
            }
            flash('success', $id === null ? 'Usuario creado.' : 'Usuario actualizado.');
            if ($id === (int) $currentUser['id_usuario']) {
                $_SESSION['user'] = $this->users->find($id);
            }
            redirect('users');
        }
        render('users/form', [
            'title' => $id === null ? 'Nuevo Usuario' : 'Editar usuario',
            'values' => $values,
            'roles' => $this->users->roles(),
            'departments' => $this->users->departments(),
            'editing' => $id !== null,
        ]);
    }

    private function userToggle(): void
    {
        $current = require_role(User::ROLE_ADMIN);
        verify_csrf();
        $id = $this->routeId();
        if ($id === (int) $current['id_usuario']) {
            flash('error', 'No puedes desactivar tu propia cuenta.');
        } else {
            $active = ($_POST['active'] ?? '0') === '1';
            $this->users->setActive($id, $active);
            flash('success', $active ? 'La cuenta fue activada si era elegible.' : 'La cuenta fue desactivada si era elegible.');
        }
        redirect('users');
    }

    private function reports(): void
    {
        require_role(User::ROLE_ADMIN);
        [$dateFrom, $dateTo, $error] = $this->reportDateRange();
        $reports = $error === null
            ? (new Report($this->db))->data($dateFrom, $dateTo)
            : ['statuses' => [], 'priorities' => [], 'categories' => [], 'resolution' => [], 'records' => []];
        render('reports/index', [
            'title' => 'Reportes y Métricas',
            'reports' => $reports,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'error' => $error,
            'chartsAvailable' => $error === null,
        ]);
    }

    private function reportsExport(): void
    {
        require_role(User::ROLE_ADMIN);
        [$dateFrom, $dateTo, $error] = $this->reportDateRange();
        if ($error !== null) {
            http_response_code(400);
            exit($error);
        }
        $report = new Report($this->db);
        $path = $report->createExcelFile($report->data($dateFrom, $dateTo));
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="registros-incidencias-' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        if (!unlink($path)) {
            error_log('No se pudo eliminar el archivo temporal de reporte ' . $path);
        }
    }

    private function reportDateRange(): array
    {
        $dateFrom = trim((string) ($_GET['from'] ?? ''));
        $dateTo = trim((string) ($_GET['to'] ?? ''));
        foreach (['Fecha desde' => $dateFrom, 'Fecha hasta' => $dateTo] as $label => $value) {
            if ($value !== '' && !$this->isValidDate($value)) {
                return [$dateFrom, $dateTo, $label . ' no es válida.'];
            }
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            return [$dateFrom, $dateTo, 'La fecha desde no puede ser posterior a la fecha hasta.'];
        }
        return [$dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null, null];
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function incidentValues(array $input): array
    {
        return [
            'titulo' => trim((string) ($input['titulo'] ?? '')),
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'id_categoria' => (string) ($input['id_categoria'] ?? ''),
            'id_prioridad' => (string) ($input['id_prioridad'] ?? ''),
        ];
    }

    private function validateIncident(array $values, array $catalogs): ?string
    {
        if ($values['titulo'] === '' || mb_strlen($values['titulo']) > 200) {
            return 'El título es obligatorio y debe tener como máximo 200 caracteres.';
        }
        if ($values['descripcion'] === '') {
            return 'La descripción es obligatoria.';
        }
        if (!$this->catalogContains($catalogs['categories'], 'id_categoria', $values['id_categoria'])
            || !$this->catalogContains($catalogs['priorities'], 'id_prioridad', $values['id_prioridad'])) {
            return 'Selecciona una categoría y prioridad válidas.';
        }
        if (isset($_FILES['attachments']) && $_FILES['attachments']['error'] !== UPLOAD_ERR_NO_FILE) {
            foreach ((array) $_FILES['attachments']['error'] as $error) {
                if ($error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE) {
                    return 'No se pudo recibir uno de los archivos adjuntos.';
                }
            }
        }
        return null;
    }

    private function validateUser(array $values, bool $passwordRequired): ?string
    {
        if ($values['nombre'] === '' || mb_strlen($values['nombre']) > 100
            || $values['apellido'] === '' || mb_strlen($values['apellido']) > 100) {
            return 'Nombre y apellido son obligatorios (máximo 100 caracteres cada uno).';
        }
        if (mb_strlen((string) ($values['telefono'] ?? '')) > 20) {
            return 'El teléfono no puede superar 20 caracteres.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) {
            return 'Indica un correo electrónico válido (máximo 150 caracteres).';
        }
        $password = $values['password'] ?? '';
        if (($passwordRequired && mb_strlen($password) < 12)
            || (!$passwordRequired && $password !== '' && mb_strlen($password) < 12)) {
            return 'La contraseña debe tener al menos 12 caracteres.';
        }
        if (strlen($password) > 72) {
            return 'La contraseña no puede superar 72 bytes.';
        }
        if (isset($values['id_rol']) && !ctype_digit((string) $values['id_rol'])) {
            return 'Selecciona un rol válido.';
        }
        if (isset($values['id_rol'])) {
            $roleIds = array_column($this->users->roles(), 'id_rol');
            if (!in_array((int) $values['id_rol'], array_map('intval', $roleIds), true)) {
                return 'Selecciona un rol válido.';
            }
        }
        if (isset($values['id_departamento']) && $values['id_departamento'] !== '') {
            $departmentIds = array_column($this->users->departments(), 'id_departamento');
            if (!in_array((int) $values['id_departamento'], array_map('intval', $departmentIds), true)) {
                return 'Selecciona un departamento válido.';
            }
        }
        return null;
    }

    private function storeUploads(array &$movedFiles): array
    {
        if (!isset($_FILES['attachments'])) {
            return [];
        }
        $files = $_FILES['attachments'];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $allowed = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'doc' => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ];
        if (count($names) > 5) {
            throw new DomainException('Puedes adjuntar un máximo de 5 archivos.');
        }
        $stored = [];
        foreach ($names as $index => $originalName) {
            if ($errors[$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errors[$index] !== UPLOAD_ERR_OK || (int) $sizes[$index] > 5 * 1024 * 1024) {
                throw new DomainException('Cada archivo debe pesar como máximo 5 MB.');
            }
            $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
            if (!isset($allowed[$extension])) {
                throw new DomainException('Formato no permitido. Usa PDF, JPG, PNG, DOC, DOCX, XLS o XLSX.');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpNames[$index]);
            if (!in_array($mime, $allowed[$extension], true)) {
                throw new DomainException('El tipo de contenido de un archivo no coincide con su extensión.');
            }
            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = ROOT_PATH . '/storage/uploads/' . $storedName;
            if (!move_uploaded_file($tmpNames[$index], $destination)) {
                throw new DomainException('No se pudo guardar un archivo adjunto.');
            }
            $movedFiles[] = $destination;
            $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string) $originalName));
            $stored[] = [
                'name' => substr($safeName ?: ('archivo.' . $extension), 0, 255),
                'path' => $storedName,
                'mime' => $mime,
                'size' => (int) $sizes[$index],
            ];
        }
        return $stored;
    }

    private function removeStoredFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                error_log('No se pudo limpiar el archivo temporal ' . $file);
            }
        }
    }

    private function canViewIncident(array $user, array $incident): bool
    {
        if ($user['role'] === User::ROLE_ADMIN) {
            return true;
        }
        if ($user['role'] === User::ROLE_TECHNICIAN) {
            return (int) $incident['id_tecnico'] === (int) $user['id_usuario'];
        }
        return (int) $incident['id_reportante'] === (int) $user['id_usuario'];
    }

    private function statusById(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        $statement = $this->db->prepare('SELECT id_estado, nombre FROM estado WHERE id_estado = :id');
        $statement->execute(['id' => (int) $id]);
        $status = $statement->fetch();
        return $status ?: null;
    }

    private function catalogContains(array $catalog, string $key, string $id): bool
    {
        foreach ($catalog as $item) {
            if ((string) $item[$key] === $id) {
                return true;
            }
        }
        return false;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return isset($exception->errorInfo[1]) && (int) $exception->errorInfo[1] === 1062;
    }

    private function routeId(): int
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            http_response_code(400);
            exit('Identificador inválido.');
        }
        return $id;
    }
}
