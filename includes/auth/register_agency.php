<?php
/**
 * Agency Registration Handler - Elsesser & Co.
 * Обработка заявки на регистрацию агентства недвижимости
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/check_auth.php';

// Редирект если уже авторизован
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$errors = [];
$success = false;
$formData = [
    'company_name' => '',
    'legal_form' => 'ooo',
    'inn' => '',
    'ogrn' => '',
    'legal_address' => '',
    'actual_address' => '',
    'contact_person' => '',
    'contact_position' => '',
    'email' => '',
    'phone' => '',
    'website' => '',
    'description' => '',
    'specialization' => [],
    'years_on_market' => '',
    'agents_count' => ''
];

// Организационно-правовые формы
$legalForms = [
    'ooo' => 'ООО (Общество с ограниченной ответственностью)',
    'ip' => 'ИП (Индивидуальный предприниматель)',
    'ao' => 'АО (Акционерное общество)',
    'zao' => 'ЗАО (Закрытое акционерное общество)',
    'pao' => 'ПАО (Публичное акционерное общество)'
];

// Специализации
$specializationOptions = [
    'sale' => 'Продажа недвижимости',
    'rent' => 'Аренда недвижимости',
    'new_buildings' => 'Новостройки',
    'commercial' => 'Коммерческая недвижимость',
    'country' => 'Загородная недвижимость'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF проверка
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Ошибка безопасности. Пожалуйста, попробуйте снова.";
    } else {
        // Получение данных
        $company_name = trim($_POST['company_name'] ?? '');
        $legal_form = $_POST['legal_form'] ?? 'ooo';
        $inn = preg_replace('/\D/', '', $_POST['inn'] ?? '');
        $ogrn = preg_replace('/\D/', '', $_POST['ogrn'] ?? '');
        $legal_address = trim($_POST['legal_address'] ?? '');
        $actual_address = trim($_POST['actual_address'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $contact_position = trim($_POST['contact_position'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $specialization = isset($_POST['specialization']) ? (array)$_POST['specialization'] : [];
        $years_on_market = filter_var($_POST['years_on_market'] ?? '', FILTER_VALIDATE_INT);
        $agents_count = filter_var($_POST['agents_count'] ?? '', FILTER_VALIDATE_INT);
        
        // Сохраняем данные для формы
        $formData = compact(
            'company_name', 'legal_form', 'inn', 'ogrn', 'legal_address', 'actual_address',
            'contact_person', 'contact_position', 'email', 'phone', 'website',
            'description', 'specialization', 'years_on_market', 'agents_count'
        );
        
        // Валидация обязательных полей
        if (empty($company_name)) {
            $errors[] = "Название компании обязательно для заполнения";
        } elseif (mb_strlen($company_name) < 3) {
            $errors[] = "Название компании должно содержать минимум 3 символа";
        }
        
        if (!array_key_exists($legal_form, $legalForms)) {
            $errors[] = "Выберите организационно-правовую форму";
        }
        
        // Валидация ИНН
        if (empty($inn)) {
            $errors[] = "ИНН обязателен для заполнения";
        } elseif ($legal_form === 'ip') {
            if (strlen($inn) !== 12) {
                $errors[] = "ИНН индивидуального предпринимателя должен содержать 12 цифр";
            }
        } else {
            if (strlen($inn) !== 10) {
                $errors[] = "ИНН юридического лица должен содержать 10 цифр";
            }
        }
        
        // Валидация ОГРН (если указан)
        if (!empty($ogrn)) {
            if ($legal_form === 'ip') {
                if (strlen($ogrn) !== 15) {
                    $errors[] = "ОГРНИП должен содержать 15 цифр";
                }
            } else {
                if (strlen($ogrn) !== 13) {
                    $errors[] = "ОГРН должен содержать 13 цифр";
                }
            }
        }
        
        if (empty($legal_address)) {
            $errors[] = "Юридический адрес обязателен для заполнения";
        }
        
        if (empty($contact_person)) {
            $errors[] = "ФИО контактного лица обязательно для заполнения";
        } elseif (mb_strlen($contact_person) < 5) {
            $errors[] = "Введите полное ФИО контактного лица";
        }
        
        // Валидация email
        if (empty($email)) {
            $errors[] = "Email обязателен для заполнения";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Некорректный формат email";
        }
        
        // Валидация телефона
        if (empty($phone)) {
            $errors[] = "Телефон обязателен для заполнения";
        } elseif (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
            $errors[] = "Некорректный формат телефона";
        }
        
        // Валидация сайта (если указан)
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            // Пробуем добавить https://
            if (filter_var('https://' . $website, FILTER_VALIDATE_URL)) {
                $website = 'https://' . $website;
                $formData['website'] = $website;
            } else {
                $errors[] = "Некорректный формат URL сайта";
            }
        }
        
        // Валидация специализации
        $validSpecializations = array_keys($specializationOptions);
        $specialization = array_filter($specialization, function($s) use ($validSpecializations) {
            return in_array($s, $validSpecializations);
        });
        
        // Согласие с условиями
        if (!isset($_POST['agree'])) {
            $errors[] = "Необходимо согласиться с условиями использования";
        }
        
        // Если нет ошибок - сохраняем заявку
        if (empty($errors)) {
            try {
                $pdo = getDBConnection();
                
                // Проверка существующего ИНН
                $stmt = $pdo->prepare("SELECT id, status FROM agency_applications WHERE inn = ?");
                $stmt->execute([$inn]);
                $existingByINN = $stmt->fetch();
                
                if ($existingByINN) {
                    if ($existingByINN['status'] === 'pending' || $existingByINN['status'] === 'reviewing') {
                        $errors[] = "Заявка с таким ИНН уже находится на рассмотрении";
                    } elseif ($existingByINN['status'] === 'approved') {
                        $errors[] = "Компания с таким ИНН уже зарегистрирована на платформе";
                    } elseif ($existingByINN['status'] === 'rejected') {
                        $errors[] = "Заявка с таким ИНН была отклонена. Обратитесь в поддержку для уточнения.";
                    }
                }
                
                // Проверка существующего email
                $stmt = $pdo->prepare("SELECT id FROM agency_applications WHERE email = ? AND status IN ('pending', 'reviewing')");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "Заявка с таким email уже находится на рассмотрении";
                }
                
                // Проверка email в users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "Пользователь с таким email уже зарегистрирован";
                }
                
                if (empty($errors)) {
                    // Вставка заявки
                    $stmt = $pdo->prepare("
                        INSERT INTO agency_applications (
                            company_name, legal_form, inn, ogrn, legal_address, actual_address,
                            contact_person, contact_position, email, phone, website,
                            description, specialization, years_on_market, agents_count, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    $stmt->execute([
                        htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'),
                        $legal_form,
                        $inn,
                        !empty($ogrn) ? $ogrn : null,
                        htmlspecialchars($legal_address, ENT_QUOTES, 'UTF-8'),
                        !empty($actual_address) ? htmlspecialchars($actual_address, ENT_QUOTES, 'UTF-8') : null,
                        htmlspecialchars($contact_person, ENT_QUOTES, 'UTF-8'),
                        !empty($contact_position) ? htmlspecialchars($contact_position, ENT_QUOTES, 'UTF-8') : null,
                        $email,
                        $phone,
                        !empty($website) ? $website : null,
                        !empty($description) ? htmlspecialchars($description, ENT_QUOTES, 'UTF-8') : null,
                        !empty($specialization) ? implode(',', $specialization) : null,
                        $years_on_market !== false ? $years_on_market : null,
                        $agents_count !== false ? $agents_count : null
                    ]);
                    
                    $success = true;
                    
                    // Логирование
                    error_log("New agency application: {$company_name} (INN: {$inn})");
                }
                
            } catch (PDOException $e) {
                error_log("Agency registration error: " . $e->getMessage());
                $errors[] = "Ошибка при отправке заявки. Пожалуйста, попробуйте позже.";
            }
        }
    }
}

// Возвращаем данные для использования в шаблоне
return [
    'errors' => $errors,
    'success' => $success,
    'formData' => $formData,
    'legalForms' => $legalForms,
    'specializationOptions' => $specializationOptions,
    'csrf_token' => generateCSRFToken()
];
?>

