document.addEventListener('DOMContentLoaded', () => {
    // Set default date values
    document.querySelectorAll('input[type=date]').forEach(i => {
        if (!i.value) i.value = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    });

    // Form validation
    const forms = document.querySelectorAll('form[method="post"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let errors = [];

            // Clear previous error messages
            form.querySelectorAll('.error-msg').forEach(el => el.remove());

            // Email validation
            const emailInputs = form.querySelectorAll('input[type="email"]');
            emailInputs.forEach(input => {
                const email = input.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email && !emailRegex.test(email)) {
                    isValid = false;
                    showError(input, 'Please enter a valid email address');
                    errors.push('Invalid email format');
                }
            });

            // Password validation
            const passwordInputs = form.querySelectorAll('input[type="password"]');
            passwordInputs.forEach(input => {
                const password = input.value;
                const minLength = input.getAttribute('minlength') || 6;
                
                if (input.name === 'password' && password) {
                    // Check minimum length
                    if (password.length < minLength) {
                        isValid = false;
                        showError(input, `Password must be at least ${minLength} characters long`);
                        errors.push('Password too short');
                    }
                    
                    // Check password strength (for register form)
                    if (form.querySelector('input[name="name"]')) { // Registration form
                        const hasUpper = /[A-Z]/.test(password);
                        const hasLower = /[a-z]/.test(password);
                        const hasNumber = /[0-9]/.test(password);
                        
                        if (!hasUpper || !hasLower || !hasNumber) {
                            isValid = false;
                            showError(input, 'Password must contain uppercase, lowercase, and number');
                            errors.push('Weak password');
                        }
                    }
                }
            });

            // Phone number validation
            const phoneInputs = form.querySelectorAll('input[name="phone"]');
            phoneInputs.forEach(input => {
                const phone = input.value.trim();
                if (phone) {
                    const phoneRegex = /^[0-9]{10}$/;
                    if (!phoneRegex.test(phone)) {
                        isValid = false;
                        showError(input, 'Phone number must be 10 digits');
                        errors.push('Invalid phone number');
                    }
                }
            });

            // Name validation
            const nameInputs = form.querySelectorAll('input[name="name"]');
            nameInputs.forEach(input => {
                const name = input.value.trim();
                if (name && name.length < 3) {
                    isValid = false;
                    showError(input, 'Name must be at least 3 characters');
                    errors.push('Name too short');
                }
            });

            // Room number validation
            const roomNumberInputs = form.querySelectorAll('input[name="room_number"]');
            roomNumberInputs.forEach(input => {
                const roomNumber = input.value.trim();
                const roomRegex = /^[A-Za-z0-9]{1,10}$/;
                if (roomNumber && !roomRegex.test(roomNumber)) {
                    isValid = false;
                    showError(input, 'Room number must be alphanumeric (max 10 characters)');
                    errors.push('Invalid room number');
                }
            });

            // Price validation
            const priceInputs = form.querySelectorAll('input[name="price"]');
            priceInputs.forEach(input => {
                const price = parseFloat(input.value);
                if (isNaN(price) || price <= 0) {
                    isValid = false;
                    showError(input, 'Price must be a positive number');
                    errors.push('Invalid price');
                }
            });

            // Date validation for bookings
            const checkInInput = form.querySelector('input[name="check_in"]');
            const checkOutInput = form.querySelector('input[name="check_out"]');
            if (checkInInput && checkOutInput) {
                const checkIn = new Date(checkInInput.value);
                const checkOut = new Date(checkOutInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (checkIn < today) {
                    isValid = false;
                    showError(checkInInput, 'Check-in date cannot be in the past');
                    errors.push('Invalid check-in date');
                }

                if (checkOut <= checkIn) {
                    isValid = false;
                    showError(checkOutInput, 'Check-out must be after check-in date');
                    errors.push('Invalid check-out date');
                }

                const daysDiff = (checkOut - checkIn) / (1000 * 60 * 60 * 24);
                if (daysDiff > 30) {
                    isValid = false;
                    showError(checkOutInput, 'Maximum booking duration is 30 days');
                    errors.push('Booking too long');
                }
            }

            // Guests validation
            const guestsInput = form.querySelector('input[name="guests"]');
            if (guestsInput) {
                const guests = parseInt(guestsInput.value);
                if (isNaN(guests) || guests < 1 || guests > 10) {
                    isValid = false;
                    showError(guestsInput, 'Number of guests must be between 1 and 10');
                    errors.push('Invalid number of guests');
                }
            }

            // Required field validation
            const requiredInputs = form.querySelectorAll('[required]');
            requiredInputs.forEach(input => {
                if (!input.value.trim() && input.type !== 'hidden') {
                    isValid = false;
                    showError(input, 'This field is required');
                    errors.push(`${input.name || 'Field'} is required`);
                }
            });

            if (!isValid) {
                e.preventDefault();
                // Show summary error
                if (errors.length > 0) {
                    const firstError = form.querySelector('.error-msg');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                return false;
            }
        });
    });

    // Real-time validation feedback
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                showError(this, 'Please enter a valid email address');
            } else {
                clearError(this);
            }
        });
    });

    document.querySelectorAll('input[type="password"]').forEach(input => {
        input.addEventListener('input', function() {
            const password = this.value;
            const minLength = this.getAttribute('minlength') || 6;
            
            if (password.length > 0 && password.length < minLength) {
                showError(this, `Password must be at least ${minLength} characters`);
            } else {
                clearError(this);
            }
        });
    });

    function showError(input, message) {
        clearError(input);
        input.classList.add('input-error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-msg';
        errorDiv.textContent = message;
        errorDiv.style.color = '#e74c3c';
        errorDiv.style.fontSize = '0.85em';
        errorDiv.style.marginTop = '4px';
        input.parentElement.appendChild(errorDiv);
    }

    function clearError(input) {
        input.classList.remove('input-error');
        const existingError = input.parentElement.querySelector('.error-msg');
        if (existingError) {
            existingError.remove();
        }
    }
});


// Toast Notification System
const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 5000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        // Determine icon based on type
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            danger: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const titles = {
            success: 'Success',
            error: 'Error',
            danger: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${icons[type] || icons.info}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${titles[type] || titles.info}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="Toast.dismiss(this)">
                <i class="fas fa-times"></i>
            </button>
            ${duration > 0 ? '<div class="toast-progress"><div class="toast-progress-bar"></div></div>' : ''}
        `;
        
        this.container.appendChild(toast);
        
        // Auto dismiss after duration
        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(toast);
            }, duration);
        }
        
        return toast;
    },
    
    dismiss(element) {
        const toast = element.classList && element.classList.contains('toast') 
            ? element 
            : element.closest('.toast');
            
        if (toast) {
            toast.classList.add('hiding');
            setTimeout(() => {
                toast.remove();
                // Remove container if empty
                if (this.container && this.container.children.length === 0) {
                    this.container.remove();
                    this.container = null;
                }
            }, 300);
        }
    },
    
    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    },
    
    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    },
    
    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    },
    
    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }
};

// Auto-show toast from PHP flash messages
document.addEventListener('DOMContentLoaded', function() {
    const flashData = document.getElementById('flash-data');
    if (flashData) {
        const type = flashData.dataset.type;
        const message = flashData.dataset.message;
        if (message) {
            Toast.show(message, type);
        }
    }
});
