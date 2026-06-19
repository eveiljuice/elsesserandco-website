<?php
/**
 * Agency Registration Page - Elsesser & Co.
 * Страница регистрации агентства недвижимости
 */

require_once __DIR__ . '/includes/config/Config.php';
$data = require_once __DIR__ . '/includes/auth/register_agency.php';
$errors = $data['errors'];
$success = $data['success'];
$formData = $data['formData'];
$legalForms = $data['legalForms'];
$specializationOptions = $data['specializationOptions'];
$csrf_token = $data['csrf_token'];

// Если пользователь подтвердил согласие на ПДн на отдельной странице,
// автопроставляем чекбокс agree в форме регистрации.
if (!empty($_GET['pd_consent']) && $_GET['pd_consent'] === '1' && empty($_SESSION['pd_consent'])) {
    $_SESSION['pd_consent'] = true;
}
$pdConsentGiven = !empty($_SESSION['pd_consent']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Регистрация агентства недвижимости на платформе Elsesser & Co. — станьте партнёром и получите доступ к инструментам продаж.">
    <title>Регистрация агентства | Elsesser & Co.</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/responsive.css">
    
    <style>
        /* Дополнительные стили для формы агентства */
        .auth-form--agency {
            max-width: 560px;
        }
        
        .form-section {
            margin-bottom: var(--space-8);
            padding-bottom: var(--space-6);
            border-bottom: 1px solid var(--color-border);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section__title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--color-navy);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .form-section__title i {
            color: var(--color-accent);
        }
        
        .form-select {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            color: var(--color-text);
            background-color: var(--color-white);
            cursor: pointer;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(194, 157, 102, 0.15);
        }
        
        .form-textarea {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            color: var(--color-text);
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(194, 157, 102, 0.15);
        }
        
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-3);
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
            font-size: var(--text-sm);
            color: var(--color-text);
        }
        
        .checkbox-item input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-group--third {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-4);
        }
        
        .success-message {
            text-align: center;
            padding: var(--space-8);
        }
        
        .success-message__icon {
            width: 80px;
            height: 80px;
            background-color: #f0fdf4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-6);
        }
        
        .success-message__icon i {
            font-size: var(--text-3xl);
            color: #16a34a;
        }
        
        .success-message__title {
            font-size: var(--text-2xl);
            color: var(--color-navy);
            margin-bottom: var(--space-3);
        }
        
        .success-message__text {
            color: var(--color-text-light);
            margin-bottom: var(--space-6);
            line-height: 1.7;
        }
        
        .info-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-md);
            padding: var(--space-4);
            margin-bottom: var(--space-6);
        }
        
        .info-box__title {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-weight: var(--font-semibold);
            color: #1e40af;
            margin-bottom: var(--space-2);
        }
        
        .info-box__text {
            font-size: var(--text-sm);
            color: #1e40af;
            line-height: 1.6;
        }
        
        @media (max-width: 640px) {
            .form-group--third {
                grid-template-columns: 1fr;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="contact.php" class="nav__link">Продать</a></li>
                        <li><a href="about.php" class="nav__link">О нас</a></li>
                    </ul>
                    <a href="login.php" class="btn btn--secondary">Вход</a>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Auth Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-wrapper">
                    <div class="auth-form auth-form--agency">
                        <?php if ($success): ?>
                        <!-- Успешная отправка заявки -->
                        <div class="success-message">
                            <div class="success-message__icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <h1 class="success-message__title">Заявка отправлена!</h1>
                            <p class="success-message__text">
                                Ваша заявка на регистрацию агентства успешно отправлена на рассмотрение.<br><br>
                                Наши специалисты проверят предоставленную информацию в течение <strong>1-3 рабочих дней</strong>. 
                                После одобрения заявки вы получите на указанный email данные для входа в личный кабинет агентства.
                            </p>
                            <a href="index.php" class="btn btn--primary">
                                <i class="fas fa-home"></i>
                                Вернуться на главную
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- Форма регистрации -->
                        <h1 class="auth-form__title">Регистрация агентства</h1>
                        <p class="auth-form__subtitle">Станьте партнёром Elsesser & Co. и получите доступ к профессиональным инструментам продаж</p>
                        
                        <div class="info-box">
                            <div class="info-box__title">
                                <i class="fas fa-info-circle"></i>
                                Только для юридических лиц
                            </div>
                            <p class="info-box__text">
                                Регистрация доступна для агентств недвижимости и застройщиков. 
                                После проверки администратором вам будут предоставлены учётные данные для входа.
                            </p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert--error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register-agency.php" class="form" id="agencyRegisterForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            
                            <!-- Раздел: Данные организации -->
                            <div class="form-section">
                                <h2 class="form-section__title">
                                    <i class="fas fa-building"></i>
                                    Данные организации
                                </h2>
                                
                                <div class="form-group">
                                    <label for="company_name" class="form-label">Название компании *</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-building"></i>
                                        <input type="text" 
                                               id="company_name" 
                                               name="company_name" 
                                               class="form-input" 
                                               placeholder="Например: Финпромстрой"
                                               value="<?= htmlspecialchars($formData['company_name']) ?>"
                                               required
                                               minlength="3"
                                               autofocus>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group--half">
                                    <div>
                                        <label for="legal_form" class="form-label">Организационно-правовая форма *</label>
                                        <select id="legal_form" name="legal_form" class="form-select" required>
                                            <?php foreach ($legalForms as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $formData['legal_form'] === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="inn" class="form-label">ИНН *</label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" 
                                                   id="inn" 
                                                   name="inn" 
                                                   class="form-input" 
                                                   placeholder="10 или 12 цифр"
                                                   value="<?= htmlspecialchars($formData['inn']) ?>"
                                                   required
                                                   pattern="[0-9]{10,12}"
                                                   maxlength="12">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ogrn" class="form-label">ОГРН / ОГРНИП <span class="form-label__optional">(рекомендуется)</span></label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-hashtag"></i>
                                        <input type="text" 
                                               id="ogrn" 
                                               name="ogrn" 
                                               class="form-input" 
                                               placeholder="13 или 15 цифр"
                                               value="<?= htmlspecialchars($formData['ogrn']) ?>"
                                               pattern="[0-9]{13,15}"
                                               maxlength="15">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="legal_address" class="form-label">Юридический адрес *</label>
                                    <textarea id="legal_address" 
                                              name="legal_address" 
                                              class="form-textarea" 
                                              placeholder="456300, г. Миасс, проспект Автозаводцев, д. 43, офис 1"
                                              required
                                              minlength="20"><?= htmlspecialchars($formData['legal_address']) ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="actual_address" class="form-label">Фактический адрес <span class="form-label__optional">(если отличается)</span></label>
                                    <textarea id="actual_address" 
                                              name="actual_address" 
                                              class="form-textarea" 
                                              placeholder="Адрес офиса, если отличается от юридического"><?= htmlspecialchars($formData['actual_address']) ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Раздел: Контактная информация -->
                            <div class="form-section">
                                <h2 class="form-section__title">
                                    <i class="fas fa-user-tie"></i>
                                    Контактная информация
                                </h2>
                                
                                <div class="form-group form-group--half">
                                    <div>
                                        <label for="contact_person" class="form-label">ФИО контактного лица *</label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-user"></i>
                                            <input type="text" 
                                                   id="contact_person" 
                                                   name="contact_person" 
                                                   class="form-input" 
                                                   placeholder="Иванов Иван Иванович"
                                                   value="<?= htmlspecialchars($formData['contact_person']) ?>"
                                                   required
                                                   minlength="5">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="contact_position" class="form-label">Должность <span class="form-label__optional">(необязательно)</span></label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-briefcase"></i>
                                            <input type="text" 
                                                   id="contact_position" 
                                                   name="contact_position" 
                                                   class="form-input" 
                                                   placeholder="Директор, менеджер..."
                                                   value="<?= htmlspecialchars($formData['contact_position']) ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group--half">
                                    <div>
                                        <label for="email" class="form-label">Email компании *</label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" 
                                                   id="email" 
                                                   name="email" 
                                                   class="form-input" 
                                                   placeholder="info@company.ru"
                                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="phone" class="form-label">Телефон *</label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-phone"></i>
                                            <input type="tel" 
                                                   id="phone" 
                                                   name="phone" 
                                                   class="form-input" 
                                                   placeholder="+7 (343) 123-45-67"
                                                   value="<?= htmlspecialchars($formData['phone']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="website" class="form-label">Сайт компании <span class="form-label__optional">(необязательно)</span></label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-globe"></i>
                                        <input type="url" 
                                               id="website" 
                                               name="website" 
                                               class="form-input" 
                                               placeholder="https://company.ru"
                                               value="<?= htmlspecialchars($formData['website']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Раздел: О компании -->
                            <div class="form-section">
                                <h2 class="form-section__title">
                                    <i class="fas fa-info-circle"></i>
                                    О компании
                                </h2>
                                
                                <div class="form-group">
                                    <label class="form-label">Специализация</label>
                                    <div class="checkbox-grid">
                                        <?php foreach ($specializationOptions as $value => $label): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" 
                                                   name="specialization[]" 
                                                   value="<?= $value ?>"
                                                   <?= in_array($value, $formData['specialization']) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group form-group--half">
                                    <div>
                                        <label for="years_on_market" class="form-label">Лет на рынке <span class="form-label__optional">(необязательно)</span></label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-calendar"></i>
                                            <input type="number" 
                                                   id="years_on_market" 
                                                   name="years_on_market" 
                                                   class="form-input" 
                                                   placeholder="10"
                                                   value="<?= htmlspecialchars($formData['years_on_market']) ?>"
                                                   min="0"
                                                   max="100">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="agents_count" class="form-label">Количество агентов <span class="form-label__optional">(необязательно)</span></label>
                                        <div class="form-input-wrapper">
                                            <i class="fas fa-users"></i>
                                            <input type="number" 
                                                   id="agents_count" 
                                                   name="agents_count" 
                                                   class="form-input" 
                                                   placeholder="25"
                                                   value="<?= htmlspecialchars($formData['agents_count']) ?>"
                                                   min="1"
                                                   max="10000">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Описание деятельности <span class="form-label__optional">(необязательно)</span></label>
                                    <textarea id="description" 
                                              name="description" 
                                              class="form-textarea" 
                                              placeholder="Расскажите о вашей компании, основных направлениях деятельности и преимуществах..."><?= htmlspecialchars($formData['description']) ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Согласие -->
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="agree" class="checkbox" required <?= $pdConsentGiven ? 'checked' : '' ?>>
                                    <span class="checkbox-custom"></span>
                                    Я подтверждаю достоверность предоставленных данных и согласен с
                                    <a href="/privacy.php?return=/register-agency.php" target="_blank">условиями партнёрства</a> и
                                    <a href="/privacy.php?return=/register-agency.php" target="_blank">политикой конфиденциальности</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn--primary btn--lg btn--full">
                                <i class="fas fa-paper-plane"></i>
                                Отправить заявку
                            </button>
                        </form>
                        
                        <p class="auth-footer">
                            Частное лицо? <a href="register.php">Зарегистрируйтесь как пользователь</a>
                        </p>
                        <p class="auth-footer">
                            Уже есть аккаунт агентства? <a href="login.php">Войдите</a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="auth-image" style="background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Партнёрство с Elsesser & Co.</h2>
                        <p>Присоединяйтесь к ведущей платформе недвижимости Екатеринбурга</p>
                        <ul class="auth-benefits">
                            <li><i class="fas fa-check"></i> Размещайте объекты без ограничений</li>
                            <li><i class="fas fa-check"></i> Получайте заявки от заинтересованных клиентов</li>
                            <li><i class="fas fa-check"></i> Удобный личный кабинет агента</li>
                            <li><i class="fas fa-check"></i> Аналитика и статистика продаж</li>
                            <li><i class="fas fa-check"></i> Техническая поддержка 24/7</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script>
        // Валидация ИНН в зависимости от типа организации
        document.getElementById('legal_form').addEventListener('change', function() {
            const innInput = document.getElementById('inn');
            const ogrnInput = document.getElementById('ogrn');
            
            if (this.value === 'ip') {
                innInput.placeholder = '12 цифр';
                innInput.pattern = '[0-9]{12}';
                ogrnInput.placeholder = '15 цифр (ОГРНИП)';
            } else {
                innInput.placeholder = '10 цифр';
                innInput.pattern = '[0-9]{10}';
                ogrnInput.placeholder = '13 цифр (ОГРН)';
            }
        });
        
        // Форматирование телефона
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value[0] === '7' || value[0] === '8') {
                    let formatted = '+7';
                    if (value.length > 1) formatted += ' (' + value.substring(1, 4);
                    if (value.length > 4) formatted += ') ' + value.substring(4, 7);
                    if (value.length > 7) formatted += '-' + value.substring(7, 9);
                    if (value.length > 9) formatted += '-' + value.substring(9, 11);
                    e.target.value = formatted;
                }
            }
        });
        
        // Только цифры для ИНН и ОГРН
        ['inn', 'ogrn'].forEach(function(id) {
            document.getElementById(id).addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });
    </script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>

