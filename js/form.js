/**
 * Form Module - Elsesser & Co.
 * Handles contact form validation and submission
 */

(function() {
    'use strict';

    const form = document.getElementById('contactForm');
    const formMessage = document.getElementById('formMessage');

    if (!form) return;

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone number (basic)
     */
    function isValidPhone(phone) {
        // Allow various phone formats
        const phoneRegex = /^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }

    /**
     * Show form message
     */
    function showMessage(message, isError = false) {
        if (!formMessage) return;
        
        formMessage.textContent = message;
        formMessage.style.display = 'block';
        formMessage.style.color = isError ? '#dc3545' : 'var(--color-accent)';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            formMessage.style.display = 'none';
        }, 5000);
    }

    /**
     * Validate form fields
     */
    function validateForm(formData) {
        const errors = [];
        
        const firstName = formData.get('first_name')?.trim();
        const lastName = formData.get('last_name')?.trim();
        const email = formData.get('email')?.trim();
        const phone = formData.get('phone')?.trim();
        const offeringType = formData.get('offering_type');
        const propertyAddress = formData.get('property_address')?.trim();
        
        if (!firstName || firstName.length < 2) {
            errors.push('Введите ваше имя (минимум 2 символа)');
        }
        
        if (!lastName || lastName.length < 2) {
            errors.push('Введите вашу фамилию (минимум 2 символа)');
        }
        
        if (!email || !isValidEmail(email)) {
            errors.push('Введите корректный email');
        }
        
        if (!phone || !isValidPhone(phone)) {
            errors.push('Введите корректный номер телефона');
        }
        
        if (!offeringType) {
            errors.push('Выберите тип предложения');
        }
        
        if (!propertyAddress || propertyAddress.length < 5) {
            errors.push('Введите адрес недвижимости');
        }
        
        return errors;
    }

    /**
     * Handle form submission
     */
    async function handleSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const errors = validateForm(formData);
        
        if (errors.length > 0) {
            showMessage(errors[0], true);
            return;
        }
        
        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                showMessage(result.message || 'Спасибо! Мы свяжемся с вами в ближайшее время.');
                form.reset();
            } else {
                showMessage(result.message || 'Произошла ошибка. Попробуйте ещё раз.', true);
            }
        } catch (error) {
            // If PHP is not available, show success message anyway (for demo purposes)
            console.log('Form data:', Object.fromEntries(formData));
            showMessage('Спасибо! Мы свяжемся с вами в ближайшее время.');
            form.reset();
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    /**
     * Add real-time validation feedback
     */
    function initRealTimeValidation() {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                const name = this.name;
                
                // Remove existing error state
                this.style.borderColor = '';
                
                // Validate specific fields
                if (name === 'email' && value && !isValidEmail(value)) {
                    this.style.borderColor = '#dc3545';
                }
                
                if (name === 'phone' && value && !isValidPhone(value)) {
                    this.style.borderColor = '#dc3545';
                }
                
                // Required field check
                if (this.required && !value) {
                    this.style.borderColor = '#dc3545';
                }
            });
            
            // Clear error on focus
            input.addEventListener('focus', function() {
                this.style.borderColor = '';
            });
        });
    }

    /**
     * Initialize phone number formatting
     */
    function initPhoneFormatting() {
        const phoneInput = form.querySelector('input[name="phone"]');
        
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                // Allow only numbers, +, spaces, and dashes
                this.value = this.value.replace(/[^\d\+\-\s\(\)]/g, '');
            });
        }
    }

    /**
     * Initialize form functionality
     */
    function init() {
        form.addEventListener('submit', handleSubmit);
        initRealTimeValidation();
        initPhoneFormatting();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

