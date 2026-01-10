/**
 * SHALOM FACTURA - JavaScript Principal
 * Funciones globales y utilidades
 */

// =====================================================
// CONFIGURACIÓN GLOBAL
// =====================================================
const ShalomApp = {
    config: {
        baseUrl: window.baseUrl || '',
        csrfToken: window.csrfToken || '',
        dateFormat: 'dd/mm/yyyy',
        currencySymbol: '$',
        decimalSeparator: '.',
        thousandSeparator: ','
    },
    
    // =====================================================
    // INICIALIZACIÓN
    // =====================================================
    init() {
        this.initDropdowns();
        this.initSidebar();
        this.initAlerts();
        this.initTooltips();
        this.initForms();
        console.log('Shalom Factura initialized');
    },
    
    // =====================================================
    // DROPDOWNS
    // =====================================================
    initDropdowns() {
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector('button, .dropdown-trigger');
            if (trigger) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    
                    // Cerrar otros dropdowns
                    document.querySelectorAll('.dropdown.active').forEach(d => {
                        if (d !== dropdown) d.classList.remove('active');
                    });
                    
                    dropdown.classList.toggle('active');
                });
            }
        });
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.active').forEach(d => {
                d.classList.remove('active');
            });
        });
    },
    
    // =====================================================
    // SIDEBAR
    // =====================================================
    initSidebar() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay?.classList.toggle('active');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar?.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    },
    
    // =====================================================
    // ALERTAS
    // =====================================================
    initAlerts() {
        // Auto-cerrar alertas después de 5 segundos
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    },
    
    // =====================================================
    // TOOLTIPS
    // =====================================================
    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = el.dataset.tooltip;
                document.body.appendChild(tooltip);
                
                const rect = el.getBoundingClientRect();
                tooltip.style.top = `${rect.top - tooltip.offsetHeight - 8}px`;
                tooltip.style.left = `${rect.left + (rect.width - tooltip.offsetWidth) / 2}px`;
                
                el._tooltip = tooltip;
            });
            
            el.addEventListener('mouseleave', () => {
                el._tooltip?.remove();
            });
        });
    },
    
    // =====================================================
    // FORMULARIOS
    // =====================================================
    initForms() {
        // Validación en tiempo real
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
            
            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
        
        // Máscara para RUC/Cédula
        document.querySelectorAll('[data-mask="identificacion"]').forEach(input => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 13);
            });
        });
        
        // Máscara para teléfono
        document.querySelectorAll('[data-mask="telefono"]').forEach(input => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9+-]/g, '').slice(0, 15);
            });
        });
        
        // Máscara para moneda
        document.querySelectorAll('[data-mask="currency"]').forEach(input => {
            input.addEventListener('blur', (e) => {
                const value = parseFloat(e.target.value.replace(/[^0-9.-]/g, '')) || 0;
                e.target.value = this.formatCurrency(value, false);
            });
        });
    },
    
    validateForm(form) {
        let isValid = true;
        form.querySelectorAll('[required]').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        return isValid;
    },
    
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        // Requerido
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'Este campo es requerido';
        }
        
        // Email
        if (isValid && field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                message = 'Email inválido';
            }
        }
        
        // Cédula
        if (isValid && field.dataset.validate === 'cedula' && value) {
            if (!this.validarCedula(value)) {
                isValid = false;
                message = 'Cédula inválida';
            }
        }
        
        // RUC
        if (isValid && field.dataset.validate === 'ruc' && value) {
            if (!this.validarRuc(value)) {
                isValid = false;
                message = 'RUC inválido';
            }
        }
        
        // Mostrar/ocultar error
        this.toggleFieldError(field, isValid, message);
        
        return isValid;
    },
    
    toggleFieldError(field, isValid, message) {
        field.classList.toggle('is-invalid', !isValid);
        
        let feedback = field.parentElement.querySelector('.invalid-feedback');
        
        if (!isValid) {
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentElement.appendChild(feedback);
            }
            feedback.textContent = message;
        } else {
            feedback?.remove();
        }
    },
    
    // =====================================================
    // VALIDACIONES ECUADOR
    // =====================================================
    validarCedula(cedula) {
        cedula = cedula.replace(/[^0-9]/g, '');
        if (cedula.length !== 10) return false;
        
        const provincia = parseInt(cedula.substring(0, 2));
        if (provincia < 1 || provincia > 24) return false;
        
        const tercerDigito = parseInt(cedula[2]);
        if (tercerDigito > 5) return false;
        
        const coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        let suma = 0;
        
        for (let i = 0; i < 9; i++) {
            let resultado = parseInt(cedula[i]) * coeficientes[i];
            if (resultado > 9) resultado -= 9;
            suma += resultado;
        }
        
        const digitoVerificador = (10 - (suma % 10)) % 10;
        return digitoVerificador === parseInt(cedula[9]);
    },
    
    validarRuc(ruc) {
        ruc = ruc.replace(/[^0-9]/g, '');
        if (ruc.length !== 13) return false;
        
        // RUC persona natural
        if (parseInt(ruc[2]) < 6) {
            if (ruc.substring(10) !== '001') return false;
            return this.validarCedula(ruc.substring(0, 10));
        }
        
        // RUC sociedad privada
        if (parseInt(ruc[2]) === 9) {
            const coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
            let suma = 0;
            
            for (let i = 0; i < 9; i++) {
                suma += parseInt(ruc[i]) * coeficientes[i];
            }
            
            const residuo = suma % 11;
            const verificador = residuo === 0 ? 0 : 11 - residuo;
            return verificador === parseInt(ruc[9]);
        }
        
        return false;
    },
    
    // =====================================================
    // FORMATEO
    // =====================================================
    formatCurrency(value, showSymbol = true) {
        const num = parseFloat(value) || 0;
        const formatted = num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        return showSymbol ? `${this.config.currencySymbol} ${formatted}` : formatted;
    },
    
    formatNumber(value, decimals = 2) {
        const num = parseFloat(value) || 0;
        return num.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    },
    
    formatDate(date) {
        if (!date) return '';
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}/${month}/${year}`;
    },
    
    parseDate(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }
        return new Date(dateStr);
    },
    
    // =====================================================
    // AJAX
    // =====================================================
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        // Agregar timeout de 60 segundos para operaciones largas
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);
        mergedOptions.signal = controller.signal;
        
        try {
            console.log('ShalomApp.request:', url, mergedOptions.method || 'GET');
            const response = await window.fetch(url, mergedOptions);
            clearTimeout(timeoutId);
            
            console.log('Response status:', response.status);
            console.log('Response content-type:', response.headers.get('content-type'));
            
            // Verificar si la respuesta es JSON válido
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Respuesta no JSON:', text.substring(0, 500));
                throw new Error('El servidor no devolvió una respuesta JSON válida');
            }
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || 'Error en la solicitud');
            }
            
            return data;
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                console.error('Request timeout');
                throw new Error('La solicitud tardó demasiado. Intente de nuevo.');
            }
            console.error('Fetch error:', error);
            throw error;
        }
    },
    
    async post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    async get(url) {
        return this.request(url, {
            method: 'GET'
        });
    },
    
    // =====================================================
    // UI HELPERS
    // =====================================================
    showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    },
    
    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    },
    
    toast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} fixed bottom-4 right-4 z-50 max-w-sm shadow-lg animate-slide-up`;
        toast.innerHTML = `
            <i data-lucide="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}" class="alert-icon"></i>
            <div class="alert-content">${message}</div>
        `;
        document.body.appendChild(toast);
        
        lucide.createIcons({ icons: toast.querySelectorAll('[data-lucide]') });
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },
    
    confirm(message, title = 'Confirmar') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="fixed inset-0 bg-black/50" data-dismiss="modal"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-shalom-primary mb-2">${title}</h3>
                    <p class="text-shalom-muted mb-6">${message}</p>
                    <div class="flex justify-end gap-3">
                        <button class="btn btn-secondary" data-action="cancel">Cancelar</button>
                        <button class="btn btn-primary" data-action="confirm">Confirmar</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('[data-dismiss="modal"]').addEventListener('click', () => {
                modal.remove();
                resolve(false);
            });
            
            modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                modal.remove();
                resolve(false);
            });
            
            modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                modal.remove();
                resolve(true);
            });
        });
    },
    
    // =====================================================
    // MODALES
    // =====================================================
    openModal(content, options = {}) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
        modal.id = options.id || 'modal-' + Date.now();
        
        const sizeClass = {
            sm: 'max-w-md',
            md: 'max-w-2xl',
            lg: 'max-w-4xl',
            xl: 'max-w-6xl',
            full: 'max-w-full mx-4'
        }[options.size || 'md'];
        
        modal.innerHTML = `
            <div class="fixed inset-0 bg-black/50 transition-opacity" data-dismiss="modal"></div>
            <div class="relative bg-white rounded-lg shadow-xl ${sizeClass} w-full max-h-[90vh] overflow-hidden flex flex-col">
                ${options.title ? `
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-shalom-primary">${options.title}</h3>
                    <button class="btn btn-icon btn-secondary btn-sm" data-dismiss="modal">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                ` : ''}
                <div class="flex-1 overflow-y-auto p-6">
                    ${content}
                </div>
                ${options.footer ? `
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    ${options.footer}
                </div>
                ` : ''}
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        lucide.createIcons();
        
        // Cerrar modal
        modal.querySelectorAll('[data-dismiss="modal"]').forEach(el => {
            el.addEventListener('click', () => this.closeModal(modal.id));
        });
        
        // ESC para cerrar
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                this.closeModal(modal.id);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
        return modal.id;
    },
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.remove();
            if (!document.querySelector('.fixed.inset-0.z-50')) {
                document.body.style.overflow = '';
            }
        }
    },
    
    closeAllModals() {
        document.querySelectorAll('.fixed.inset-0.z-50').forEach(modal => modal.remove());
        document.body.style.overflow = '';
    }
};

// =====================================================
// HANDSONTABLE HELPERS
// =====================================================
const HandsontableHelpers = {
    defaultSettings: {
        licenseKey: 'non-commercial-and-evaluation',
        stretchH: 'all',
        autoWrapRow: true,
        height: 'auto',
        rowHeaders: true,
        colHeaders: true,
        contextMenu: true,
        manualColumnResize: true,
        manualRowResize: true,
        filters: true,
        dropdownMenu: true,
        columnSorting: true,
        className: 'htCenter',
        language: 'es-MX'
    },
    
    currencyRenderer(instance, td, row, col, prop, value) {
        Handsontable.renderers.NumericRenderer.apply(this, arguments);
        td.innerHTML = ShalomApp.formatCurrency(value);
        td.style.textAlign = 'right';
    },
    
    percentRenderer(instance, td, row, col, prop, value) {
        Handsontable.renderers.NumericRenderer.apply(this, arguments);
        td.innerHTML = (parseFloat(value) || 0).toFixed(2) + '%';
        td.style.textAlign = 'right';
    },
    
    dateRenderer(instance, td, row, col, prop, value) {
        td.innerHTML = ShalomApp.formatDate(value);
        td.style.textAlign = 'center';
    },
    
    statusRenderer(instance, td, row, col, prop, value, cellProperties) {
        const statusConfig = cellProperties.statusConfig || {};
        const config = statusConfig[value] || { label: value, class: 'badge-muted' };
        
        td.innerHTML = `<span class="badge ${config.class}">${config.label}</span>`;
        td.style.textAlign = 'center';
    }
};

// =====================================================
// INICIALIZACIÓN
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
    ShalomApp.init();
});

// Exportar globalmente
window.ShalomApp = ShalomApp;
window.HandsontableHelpers = HandsontableHelpers;