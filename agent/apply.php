<?php
/**
 * Apply to become an agent (private person).
 *
 * Доступно только залогиненным user (role=user, role != 'agent' && role != 'admin').
 * Отправляет заявку в agent_applications. Админ одобряет в /admin/agent-applications.php.
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireLogin('/agent/apply.php');

$pdo = getDBConnection();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Агенты и админы не могут подавать заявку
if (in_array($userRole, ['agent', 'admin'], true)) {
    header('Location: /agent/dashboard.php');
    exit;
}

// Данные текущего user
$stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Уже есть заявка?
$stmt = $pdo->prepare("SELECT id, status, created_at, rejection_reason, resume_filename FROM agent_applications WHERE user_id = ?");
$stmt->execute([$userId]);
$existingApp = $stmt->fetch();

$errors = [];
$success = false;
$formData = [
    'full_name'        => trim(($userData['last_name'] ?? '') . ' ' . ($userData['first_name'] ?? '')),
    'phone'            => $userData['phone'] ?? '',
    'email'            => $userData['email'] ?? '',
    'region'           => '',
    'experience_years' => '',
    'specialization'   => [],
    'about'            => '',
    'motivation'       => '',
];

// Конфигурация загрузки резюме
const RESUME_MAX_BYTES = 10 * 1024 * 1024; // 10 MB
const RESUME_MIME_ALLOWED = [
    'application/pdf'                                                      => 'pdf',
    'application/msword'                                                   => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/rtf'                                                      => 'rtf',
    'text/plain'                                                           => 'txt',
    'image/jpeg'                                                           => 'jpg',
    'image/png'                                                            => 'png',
];
const RESUME_EXT_ALLOWED = ['pdf', 'doc', 'docx', 'rtf', 'txt', 'jpg', 'jpeg', 'png'];
const RESUME_UPLOAD_DIR  = __DIR__ . '/../uploads/agent_resumes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $formData['full_name']        = trim($_POST['full_name'] ?? '');
        $formData['phone']            = trim($_POST['phone'] ?? '');
        $formData['email']            = trim($_POST['email'] ?? '');
        $formData['region']           = trim($_POST['region'] ?? '');
        $formData['experience_years'] = trim($_POST['experience_years'] ?? '');
        $formData['about']            = trim($_POST['about'] ?? '');
        $formData['motivation']       = trim($_POST['motivation'] ?? '');
        $formData['specialization']   = isset($_POST['specialization']) ? (array)$_POST['specialization'] : [];

        // Валидация текстовых полей
        if (empty($formData['full_name']) || mb_strlen($formData['full_name']) < 5) {
            $errors[] = 'Укажите полное ФИО (минимум 5 символов)';
        }
        if (empty($formData['phone']) || !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $formData['phone'])) {
            $errors[] = 'Корректный номер телефона (10–20 цифр)';
        }
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Корректный email';
        }
        $validSpec = ['sale', 'rent', 'new_buildings', 'commercial', 'country'];
        $formData['specialization'] = array_values(array_filter(
            $formData['specialization'],
            fn($s) => in_array($s, $validSpec, true)
        ));
        if (empty($formData['specialization'])) {
            $errors[] = 'Выберите хотя бы одну специализацию';
        }
        $expYears = filter_var($formData['experience_years'], FILTER_VALIDATE_INT);
        if ($expYears === false || $expYears < 0 || $expYears > 70) {
            $errors[] = 'Укажите корректный опыт работы (0–70 лет)';
        }
        if (mb_strlen($formData['about']) < 30) {
            $errors[] = 'Расскажите о себе подробнее (минимум 30 символов)';
        }
        if (mb_strlen($formData['motivation']) < 20) {
            $errors[] = 'Укажите, почему хотите стать агентом (минимум 20 символов)';
        }

        // Если заявка уже есть и она в активном статусе — нельзя создать вторую
        if ($existingApp && $existingApp['status'] !== 'rejected') {
            $errors[] = 'У вас уже есть активная заявка';
        }

        // ========== Обработка резюме ==========
        $resumePath = null;
        $resumeFilename = null;
        $resumeSize = null;
        $resumeUploadError = null;

        if (!isset($_FILES['resume'])) {
            $errors[] = 'Прикрепите файл с резюме (PDF / DOC / DOCX / RTF / TXT / JPG / PNG, до 10 МБ)';
        } else {
            $file = $_FILES['resume'];

            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Прикрепите файл с резюме (PDF / DOC / DOCX / RTF / TXT / JPG / PNG, до 10 МБ)';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'Файл превышает максимально разрешённый размер на сервере (php.ini)',
                    UPLOAD_ERR_FORM_SIZE  => 'Файл превышает максимально разрешённый размер формы',
                    UPLOAD_ERR_PARTIAL    => 'Файл загружен только частично',
                    UPLOAD_ERR_NO_TMP_DIR => 'Ошибка сервера: нет временной папки',
                    UPLOAD_ERR_CANT_WRITE => 'Ошибка сервера: не удалось записать файл',
                    UPLOAD_ERR_EXTENSION  => 'Загрузка остановлена расширением PHP',
                ];
                $errors[] = $uploadErrors[$file['error']] ?? 'Не удалось загрузить файл';
                $resumeUploadError = true;
            } else {
                // Проверка размера
                if ($file['size'] <= 0) {
                    $errors[] = 'Файл пустой';
                    $resumeUploadError = true;
                } elseif ($file['size'] > RESUME_MAX_BYTES) {
                    $errors[] = 'Размер файла ' . round($file['size'] / 1024 / 1024, 1) . ' МБ превышает лимит 10 МБ';
                    $resumeUploadError = true;
                }

                // Проверка расширения
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!$resumeUploadError && !in_array($ext, RESUME_EXT_ALLOWED, true)) {
                    $errors[] = 'Недопустимый формат файла .' . htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') . '. Разрешено: ' . implode(', ', array_map(fn($e) => '.' . $e, RESUME_EXT_ALLOWED));
                    $resumeUploadError = true;
                }

                // Проверка MIME-типа (через finfo)
                if (!$resumeUploadError) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $detectedMime = $finfo->file($file['tmp_name']) ?: '';
                    if (!isset(RESUME_MIME_ALLOWED[$detectedMime]) && !in_array($ext, ['doc', 'txt'], true)) {
                        $errors[] = 'Содержимое файла не соответствует расширению (MIME: ' . htmlspecialchars($detectedMime, ENT_QUOTES, 'UTF-8') . ')';
                        $resumeUploadError = true;
                    }
                }

                if (!$resumeUploadError) {
                    // Создаём каталог
                    if (!is_dir(RESUME_UPLOAD_DIR)) {
                        @mkdir(RESUME_UPLOAD_DIR, 0755, true);
                    }
                    if (!is_dir(RESUME_UPLOAD_DIR) || !is_writable(RESUME_UPLOAD_DIR)) {
                        $errors[] = 'Сервер не может сохранить файл. Обратитесь к администратору.';
                        $resumeUploadError = true;
                    } else {
                        // Уникальное имя: timestamp_userid_hash.ext
                        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                        $safeBase = mb_substr($safeBase ?? 'resume', 0, 40);
                        $hash = bin2hex(random_bytes(6));
                        $storedName = sprintf('%d_%d_%s_%s.%s', time(), $userId, $hash, $safeBase, $ext);
                        $fullPath = RESUME_UPLOAD_DIR . '/' . $storedName;

                        if (!@move_uploaded_file($file['tmp_name'], $fullPath)) {
                            $errors[] = 'Не удалось сохранить файл на сервере';
                            $resumeUploadError = true;
                        } else {
                            @chmod($fullPath, 0644);
                            $resumePath = 'uploads/agent_resumes/' . $storedName;
                            $resumeFilename = mb_substr($file['name'], 0, 200);
                            $resumeSize = (int)$file['size'];
                        }
                    }
                }
            }
        }

        if (!isset($_POST['agree'])) {
            $errors[] = 'Подтвердите согласие с условиями';
        }

        if (empty($errors)) {
            try {
                if ($existingApp && $existingApp['status'] === 'rejected') {
                    // Обновляем отклонённую заявку (новый шанс). Если есть старое резюме — удалим файл.
                    if (!empty($existingApp['resume_filename'])) {
                        @unlink(__DIR__ . '/../' . $existingApp['resume_path']);
                    }
                    $stmt = $pdo->prepare("
                        UPDATE agent_applications SET
                            full_name = ?, phone = ?, email = ?, region = ?,
                            experience_years = ?, specialization = ?, about = ?, motivation = ?,
                            resume_path = ?, resume_filename = ?, resume_size = ?,
                            status = 'pending', rejection_reason = NULL,
                            reviewed_by = NULL, reviewed_at = NULL
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $formData['full_name'],
                        $formData['phone'],
                        $formData['email'],
                        $formData['region'] ?: null,
                        $expYears,
                        implode(',', $formData['specialization']),
                        $formData['about'],
                        $formData['motivation'],
                        $resumePath,
                        $resumeFilename,
                        $resumeSize,
                        $userId,
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO agent_applications
                            (user_id, full_name, phone, email, region, experience_years, specialization,
                             about, motivation, resume_path, resume_filename, resume_size, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $userId,
                        $formData['full_name'],
                        $formData['phone'],
                        $formData['email'],
                        $formData['region'] ?: null,
                        $expYears,
                        implode(',', $formData['specialization']),
                        $formData['about'],
                        $formData['motivation'],
                        $resumePath,
                        $resumeFilename,
                        $resumeSize,
                    ]);
                }
                $success = true;
                $existingApp = $pdo->query("SELECT id, status, created_at, rejection_reason, resume_filename FROM agent_applications WHERE user_id = $userId")->fetch();
            } catch (PDOException $e) {
                // Удаляем загруженный файл при ошибке БД
                if ($resumePath && file_exists(__DIR__ . '/../' . $resumePath)) {
                    @unlink(__DIR__ . '/../' . $resumePath);
                }
                error_log('agent apply error: ' . $e->getMessage());
                $errors[] = 'Не удалось отправить заявку. Попробуйте позже.';
            }
        }
    }
}

$specializationOptions = [
    'sale'         => 'Продажа недвижимости',
    'rent'         => 'Аренда недвижимости',
    'new_buildings'=> 'Новостройки',
    'commercial'   => 'Коммерческая недвижимость',
    'country'      => 'Загородная недвижимость',
];
$pageTitle = 'Стать агентом';

// Состояние чекбоксов из POST при ошибке
$selectedSpec = $formData['specialization'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>

    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="/index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="/properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="/properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="/contact.php" class="nav__link">Продать</a></li>
                        <li><a href="/about.php" class="nav__link">О нас</a></li>
                    </ul>
                    <a href="/dashboard.php" class="btn btn--secondary">Личный кабинет</a>
                </nav>
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/../includes/mobile-menu.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-wrapper">
                    <div class="auth-form">
                        <h1 class="auth-form__title">Стать агентом</h1>
                        <p class="auth-form__subtitle">
                            Заполните заявку, чтобы получить возможность добавлять объекты на сайт
                        </p>

                        <?php if (!empty($errors)): ?>
                        <div class="alert alert--error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                        <div class="alert alert--success">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Заявка отправлена!</strong>
                                <p style="margin: 6px 0 0;">Мы рассмотрим её в течение 1–3 рабочих дней и пришлём результат на ваш email. После одобрения у вас появится доступ к панели агента.</p>
                                <p style="margin: 10px 0 0;"><a href="/dashboard.php" class="btn btn--primary btn--sm">Вернуться в личный кабинет</a></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($existingApp && !$success && in_array($existingApp['status'], ['pending', 'reviewing'], true)): ?>
                        <div class="alert alert--info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Заявка уже на рассмотрении</strong>
                                <p style="margin: 6px 0 0;">
                                    Подана: <?= date('d.m.Y H:i', strtotime($existingApp['created_at'])) ?>.
                                    Статус: <strong><?= match($existingApp['status']) {
                                        'pending' => 'ожидает рассмотрения',
                                        'reviewing' => 'в процессе проверки',
                                        default => $existingApp['status']
                                    } ?></strong>.
                                    Дождитесь решения — мы пришлём email.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($existingApp && $existingApp['status'] === 'rejected' && !$success): ?>
                        <div class="alert alert--warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Предыдущая заявка отклонена</strong>
                                <?php if (!empty($existingApp['rejection_reason'])): ?>
                                <p style="margin: 6px 0 0;">Причина: <?= htmlspecialchars($existingApp['rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <p style="margin: 6px 0 0;">Вы можете подать заявку повторно с обновлёнными данными и резюме.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!$success && (!$existingApp || $existingApp['status'] === 'rejected')): ?>
                        <form method="POST" action="apply.php" class="form" enctype="multipart/form-data" id="applyForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="form-group">
                                <label for="full_name" class="form-label">ФИО <span class="form-label__required">*</span></label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text"
                                           id="full_name"
                                           name="full_name"
                                           class="form-input"
                                           required minlength="5" maxlength="150"
                                           value="<?= htmlspecialchars($formData['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="Иванов Иван Иванович">
                                </div>
                            </div>

                            <div class="form-group form-group--half">
                                <div>
                                    <label for="phone" class="form-label">Телефон <span class="form-label__required">*</span></label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-phone"></i>
                                        <input type="tel"
                                               id="phone"
                                               name="phone"
                                               class="form-input"
                                               required maxlength="20"
                                               value="<?= htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="+7 912 345-67-89">
                                    </div>
                                </div>
                                <div>
                                    <label for="email" class="form-label">Email <span class="form-label__required">*</span></label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email"
                                               id="email"
                                               name="email"
                                               class="form-input"
                                               required maxlength="255"
                                               value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="your@email.com">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group form-group--half">
                                <div>
                                    <label for="region" class="form-label">Регион работы</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <input type="text"
                                               id="region"
                                               name="region"
                                               class="form-input"
                                               maxlength="100"
                                               value="<?= htmlspecialchars($formData['region'], ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="Москва, СПб, Казань…">
                                    </div>
                                </div>
                                <div>
                                    <label for="experience_years" class="form-label">Опыт (лет) <span class="form-label__required">*</span></label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-briefcase"></i>
                                        <input type="number"
                                               id="experience_years"
                                               name="experience_years"
                                               class="form-input"
                                               min="0" max="70" required
                                               value="<?= htmlspecialchars($formData['experience_years'], ENT_QUOTES, 'UTF-8') ?>"
                                               placeholder="0">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Специализация <span class="form-label__required">*</span></label>
                                <p class="form-hint" style="margin: 0 0 10px;">Выберите одно или несколько направлений</p>
                                <div class="form-checkbox-grid">
                                    <?php foreach ($specializationOptions as $val => $label): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialization[]" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" class="checkbox"
                                               <?= in_array($val, $selectedSpec, true) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <span class="checkbox-text"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="about" class="form-label">О себе <span class="form-label__required">*</span></label>
                                <p class="form-hint" style="margin: 0 0 6px;">Опыт, профессиональные достижения, навыки. Минимум 30 символов.</p>
                                <textarea id="about" name="about" class="form-input form-input--textarea" rows="4" required minlength="30" maxlength="2000"
                                          placeholder="Пример: 5 лет в сфере продаж новостроек, знание всех районов города, наработанная база клиентов…"><?= htmlspecialchars($formData['about'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="motivation" class="form-label">Почему хотите стать агентом? <span class="form-label__required">*</span></label>
                                <p class="form-hint" style="margin: 0 0 6px;">Минимум 20 символов.</p>
                                <textarea id="motivation" name="motivation" class="form-input form-input--textarea" rows="3" required minlength="20" maxlength="1000"
                                          placeholder="Что вас мотивирует работать с нами…"><?= htmlspecialchars($formData['motivation'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Резюме <span class="form-label__required">*</span></label>
                                <p class="form-hint" style="margin: 0 0 6px;">PDF, DOC, DOCX, RTF, TXT, JPG или PNG. Максимум 10 МБ.</p>
                                <div class="form-file-wrapper">
                                    <input type="file"
                                           id="resume"
                                           name="resume"
                                           class="form-input form-input--file"
                                           required
                                           accept=".pdf,.doc,.docx,.rtf,.txt,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/rtf,text/plain,image/jpeg,image/png">
                                    <div class="form-file-hint" id="resumeHint">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Нажмите «Выбрать файл» или перетащите его сюда</span>
                                    </div>
                                </div>
                                <div class="form-file-info" id="resumeInfo" style="display:none;">
                                    <i class="fas fa-file"></i>
                                    <span id="resumeFileName"></span>
                                    <span class="form-file-size" id="resumeFileSize"></span>
                                    <button type="button" class="form-file-clear" id="resumeClear" aria-label="Удалить файл">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="agree" class="checkbox" required>
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-text">Я согласен с <a href="/privacy.php" target="_blank">условиями</a> и подтверждаю, что данные корректны</span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn--primary btn--lg btn--full" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Отправить заявку
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="auth-image" style="background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Работа с нами</h2>
                        <p>Добавляйте объекты, получайте заявки от клиентов, ведите свою CRM — всё в одном месте</p>
                        <ul class="auth-benefits">
                            <li><i class="fas fa-check"></i> Бесплатное размещение объектов</li>
                            <li><i class="fas fa-check"></i> Прямые заявки от покупателей</li>
                            <li><i class="fas fa-check"></i> Удобная CRM-панель</li>
                            <li><i class="fas fa-check"></i> Поддержка команды 24/7</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    // Live preview of selected resume file
    (function () {
        var fileInput = document.getElementById('resume');
        var hint = document.getElementById('resumeHint');
        var info = document.getElementById('resumeInfo');
        var nameEl = document.getElementById('resumeFileName');
        var sizeEl = document.getElementById('resumeFileSize');
        var clearBtn = document.getElementById('resumeClear');
        var wrapper = document.querySelector('.form-file-wrapper');
        var form = document.getElementById('applyForm');
        var submitBtn = document.getElementById('submitBtn');
        if (!fileInput) return;

        function humanSize(bytes) {
            if (bytes < 1024) return bytes + ' Б';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
            return (bytes / 1024 / 1024).toFixed(2) + ' МБ';
        }
        function showFile(file) {
            if (!file) { clearFile(); return; }
            hint.style.display = 'none';
            info.style.display = 'flex';
            nameEl.textContent = file.name;
            sizeEl.textContent = '(' + humanSize(file.size) + ')';
            wrapper.classList.add('has-file');
        }
        function clearFile() {
            hint.style.display = '';
            info.style.display = 'none';
            nameEl.textContent = '';
            sizeEl.textContent = '';
            wrapper.classList.remove('has-file');
            fileInput.value = '';
        }
        fileInput.addEventListener('change', function () {
            showFile(fileInput.files[0]);
        });
        if (clearBtn) clearBtn.addEventListener('click', clearFile);

        // Drag & drop
        ['dragover', 'dragenter'].forEach(function (ev) {
            wrapper.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            wrapper.addEventListener(ev, function (e) {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.remove('is-drag');
            });
        });
        wrapper.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showFile(e.dataTransfer.files[0]);
            }
        });

        // Client-side guard: блокируем submit если файл не выбран или > 10MB
        if (form && submitBtn) {
            form.addEventListener('submit', function (e) {
                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    fileInput.focus();
                    return;
                }
                var f = fileInput.files[0];
                if (f.size > 10 * 1024 * 1024) {
                    e.preventDefault();
                    alert('Файл слишком большой. Максимум 10 МБ.');
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправляем…';
            });
        }
    })();
    </script>

    <?php include __DIR__ . '/../includes/cookie-banner.php'; ?>
</body>
</html>