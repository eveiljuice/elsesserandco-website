<?php
/**
 * Email Notifications - Elsesser & Co.
 * Система email-уведомлений
 */

// Конфигурация email
define('EMAIL_FROM', 'noreply@elsesserandco.com');
define('EMAIL_FROM_NAME', 'Elsesser & Co.');
define('SITE_URL', 'http://localhost'); // Изменить в продакшене

/**
 * Базовая функция отправки email
 * 
 * @param string $to Email получателя
 * @param string $subject Тема письма
 * @param string $body HTML тело письма
 * @return bool
 */
function sendEmail(string $to, string $subject, string $body): bool {
    // Заголовки письма
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_FROM,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Обертка в HTML шаблон
    $htmlBody = getEmailTemplate($subject, $body);
    
    // Логирование попытки отправки
    error_log("Email sent to: $to, Subject: $subject");
    
    // Отправка
    return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * HTML шаблон письма
 * 
 * @param string $title Заголовок
 * @param string $content Контент
 * @return string
 */
function getEmailTemplate(string $title, string $content): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1a2447; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">Elsesser & Co.</h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.8); font-size: 14px;">Элитная недвижимость в Дубае</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 40px;">
                            {$content}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 25px 40px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px; color: #6b7280; font-size: 12px; text-align: center;">
                                © 2024 ООО "Эльсессер и Ко". Все права защищены.
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; text-align: center;">
                                г. Миасс, проспект Автозаводцев, 43 | +7 (3513) 50-50-50
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Письмо приветствия при регистрации
 * 
 * @param string $email Email пользователя
 * @param string $name Имя пользователя
 * @return bool
 */
function sendRegistrationEmail(string $email, string $name): bool {
    $subject = "Добро пожаловать в Elsesser & Co.!";
    
    $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Здравствуйте, {$name}!</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Благодарим вас за регистрацию в Elsesser & Co. — вашем надёжном партнёре на рынке недвижимости Дубая.
</p>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Теперь вы можете:
</p>
<ul style="margin: 0 0 24px; padding-left: 20px; color: #4b5563; font-size: 16px; line-height: 1.8;">
    <li>Сохранять понравившиеся объекты в избранное</li>
    <li>Получать уведомления о новых предложениях</li>
    <li>Связываться с нашими агентами напрямую</li>
    <li>Отправлять запросы на просмотр</li>
</ul>
<div style="text-align: center; margin: 32px 0;">
    <a href="%SITE_URL%/properties.php" style="display: inline-block; background-color: #00736c; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
        Смотреть объекты
    </a>
</div>
<p style="margin: 0; color: #6b7280; font-size: 14px;">
    Если у вас есть вопросы, свяжитесь с нами: <a href="mailto:info@elsesserandco.com" style="color: #00736c;">info@elsesserandco.com</a>
</p>
HTML;
    
    $body = str_replace('%SITE_URL%', SITE_URL, $body);
    
    return sendEmail($email, $subject, $body);
}

/**
 * Уведомление агенту о новой заявке
 * 
 * @param string $agentEmail Email агента
 * @param string $propertyTitle Название объекта
 * @param string $clientName Имя клиента
 * @param string $clientEmail Email клиента
 * @param string $clientPhone Телефон клиента
 * @param string $message Сообщение клиента
 * @return bool
 */
function sendRequestNotification(string $agentEmail, string $propertyTitle, string $clientName, string $clientEmail, string $clientPhone = '', string $message = ''): bool {
    $subject = "Новая заявка: " . $propertyTitle;
    
    $phoneRow = '';
    if (!empty($clientPhone)) {
        $phoneRow = "<tr><td style='padding: 8px 0; color: #6b7280;'>Телефон:</td><td style='padding: 8px 0; color: #1a2447; font-weight: 500;'><a href='tel:{$clientPhone}' style='color: #00736c;'>{$clientPhone}</a></td></tr>";
    }
    
    $messageBlock = '';
    if (!empty($message)) {
        $messageBlock = <<<HTML
<div style="margin-top: 24px; padding: 20px; background-color: #f9fafb; border-radius: 8px; border-left: 4px solid #00736c;">
    <p style="margin: 0 0 8px; color: #6b7280; font-size: 14px;">Сообщение клиента:</p>
    <p style="margin: 0; color: #1a2447; font-size: 15px; line-height: 1.6;">{$message}</p>
</div>
HTML;
    }
    
    $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Новая заявка на просмотр</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Вы получили новую заявку на объект:
</p>
<div style="padding: 16px 20px; background-color: #e8f5f4; border-radius: 8px; margin-bottom: 24px;">
    <p style="margin: 0; color: #00736c; font-size: 18px; font-weight: 600;">{$propertyTitle}</p>
</div>
<h3 style="margin: 0 0 16px; color: #1a2447; font-size: 18px;">Контактные данные клиента</h3>
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px 0; color: #6b7280; width: 100px;">Имя:</td>
        <td style="padding: 8px 0; color: #1a2447; font-weight: 500;">{$clientName}</td>
    </tr>
    <tr>
        <td style="padding: 8px 0; color: #6b7280;">Email:</td>
        <td style="padding: 8px 0; color: #1a2447; font-weight: 500;"><a href="mailto:{$clientEmail}" style="color: #00736c;">{$clientEmail}</a></td>
    </tr>
    {$phoneRow}
</table>
{$messageBlock}
<div style="text-align: center; margin: 32px 0;">
    <a href="%SITE_URL%/agent/requests.php" style="display: inline-block; background-color: #00736c; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
        Открыть заявку
    </a>
</div>
HTML;
    
    $body = str_replace('%SITE_URL%', SITE_URL, $body);
    
    return sendEmail($agentEmail, $subject, $body);
}

/**
 * Уведомление пользователю о принятой заявке
 * 
 * @param string $email Email пользователя
 * @param string $name Имя пользователя
 * @param string $propertyTitle Название объекта
 * @return bool
 */
function sendRequestConfirmation(string $email, string $name, string $propertyTitle): bool {
    $subject = "Заявка принята — " . $propertyTitle;
    
    $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Здравствуйте, {$name}!</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Ваша заявка на объект успешно принята:
</p>
<div style="padding: 16px 20px; background-color: #e8f5f4; border-radius: 8px; margin-bottom: 24px;">
    <p style="margin: 0; color: #00736c; font-size: 18px; font-weight: 600;">{$propertyTitle}</p>
</div>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Наш агент свяжется с вами в ближайшее время для уточнения деталей и назначения просмотра.
</p>
<p style="margin: 0; color: #6b7280; font-size: 14px;">
    Если у вас есть вопросы, не стесняйтесь связаться с нами по телефону +971 50 123 4567
</p>
HTML;
    
    return sendEmail($email, $subject, $body);
}

/**
 * Уведомление о новом сообщении в чате
 * 
 * @param string $email Email получателя
 * @param string $receiverName Имя получателя
 * @param string $senderName Имя отправителя
 * @param string $messagePreview Превью сообщения
 * @return bool
 */
function sendMessageNotification(string $email, string $receiverName, string $senderName, string $messagePreview): bool {
    $subject = "Новое сообщение от " . $senderName;
    
    $preview = htmlspecialchars(mb_substr($messagePreview, 0, 100));
    if (mb_strlen($messagePreview) > 100) {
        $preview .= '...';
    }
    
    $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Здравствуйте, {$receiverName}!</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Вы получили новое сообщение от <strong>{$senderName}</strong>:
</p>
<div style="padding: 20px; background-color: #f9fafb; border-radius: 8px; border-left: 4px solid #00736c; margin-bottom: 24px;">
    <p style="margin: 0; color: #1a2447; font-size: 15px; line-height: 1.6; font-style: italic;">"{$preview}"</p>
</div>
<div style="text-align: center; margin: 32px 0;">
    <a href="%SITE_URL%/chat.php" style="display: inline-block; background-color: #00736c; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
        Ответить
    </a>
</div>
HTML;
    
    $body = str_replace('%SITE_URL%', SITE_URL, $body);
    
    return sendEmail($email, $subject, $body);
}

/**
 * Уведомление об изменении статуса заявки
 * 
 * @param string $email Email пользователя
 * @param string $name Имя пользователя
 * @param string $propertyTitle Название объекта
 * @param string $newStatus Новый статус
 * @return bool
 */
function sendStatusChangeNotification(string $email, string $name, string $propertyTitle, string $newStatus): bool {
    $statusLabels = [
        'contacted' => 'Агент связался',
        'scheduled' => 'Просмотр запланирован',
        'completed' => 'Завершена',
        'cancelled' => 'Отменена'
    ];
    
    $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
    $subject = "Статус заявки изменён — " . $statusLabel;
    
    $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Здравствуйте, {$name}!</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Статус вашей заявки на объект был обновлён:
</p>
<div style="padding: 16px 20px; background-color: #e8f5f4; border-radius: 8px; margin-bottom: 16px;">
    <p style="margin: 0; color: #00736c; font-size: 18px; font-weight: 600;">{$propertyTitle}</p>
</div>
<div style="padding: 12px 20px; background-color: #f3f4f6; border-radius: 8px; margin-bottom: 24px; text-align: center;">
    <p style="margin: 0; color: #1a2447; font-size: 16px;">Новый статус: <strong>{$statusLabel}</strong></p>
</div>
<div style="text-align: center; margin: 32px 0;">
    <a href="%SITE_URL%/dashboard.php" style="display: inline-block; background-color: #00736c; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: 600;">
        Личный кабинет
    </a>
</div>
HTML;
    
    $body = str_replace('%SITE_URL%', SITE_URL, $body);
    
    return sendEmail($email, $subject, $body);
}
