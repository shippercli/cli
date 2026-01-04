// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
    setupEventListeners();
    animateCounters();
    detectEnvironment();
});

/**
 * Initialize the application
 */
function initializeApp() {
    console.log('Deployer Frontend Example loaded successfully!');
    
    // Track page view
    incrementCounter('visitorCount');
    
    // Log deployment info
    logDeploymentInfo();
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // CTA Button click handler
    const ctaButton = document.getElementById('ctaButton');
    if (ctaButton) {
        ctaButton.addEventListener('click', handleCTAClick);
    }
    
    // Contact form submission handler
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', handleFormSubmit);
    }
    
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.addEventListener('click', handleSmoothScroll);
    });
    
    // Logo click animation
    const logo = document.getElementById('logo');
    if (logo) {
        logo.addEventListener('click', handleLogoClick);
    }
}

/**
 * Handle CTA button click
 */
function handleCTAClick() {
    showNotification('Welcome! This is a sample deployment.', 'success');
    incrementCounter('deploymentCount');
    
    // Scroll to features section
    const featuresSection = document.getElementById('features');
    if (featuresSection) {
        featuresSection.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Handle form submission
 * @param {Event} event - The submit event
 */
function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Validate form data
    if (!validateFormData(data)) {
        showFormMessage('Please fill in all fields correctly.', 'error');
        return;
    }
    
    // Simulate form submission
    console.log('Form submitted:', data);
    
    // Show success message
    showFormMessage('Thank you! Your message has been received.', 'success');
    
    // Reset form
    form.reset();
    
    // Increment deployment counter as a demo action
    incrementCounter('deploymentCount');
}

/**
 * Validate form data
 * @param {Object} data - Form data to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function validateFormData(data) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    return (
        data.name && data.name.trim().length > 0 &&
        data.email && emailRegex.test(data.email) &&
        data.message && data.message.trim().length > 0
    );
}

/**
 * Show form message
 * @param {string} message - The message to display
 * @param {string} type - The message type (success or error)
 */
function showFormMessage(message, type) {
    const formMessage = document.getElementById('formMessage');
    if (!formMessage) return;
    
    formMessage.textContent = message;
    formMessage.className = `form-message ${type}`;
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        formMessage.className = 'form-message';
    }, 5000);
}

/**
 * Handle smooth scrolling for navigation links
 * @param {Event} event - The click event
 */
function handleSmoothScroll(event) {
    event.preventDefault();
    
    const targetId = event.target.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);
    
    if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Handle logo click animation
 */
function handleLogoClick() {
    const logo = document.getElementById('logo');
    if (!logo) return;
    
    logo.style.transform = 'rotate(360deg)';
    logo.style.transition = 'transform 0.6s ease';
    
    setTimeout(() => {
        logo.style.transform = 'rotate(0deg)';
    }, 600);
}

/**
 * Animate counters with counting effect
 */
function animateCounters() {
    const deploymentCount = document.getElementById('deploymentCount');
    const visitorCount = document.getElementById('visitorCount');
    
    if (deploymentCount) {
        animateCounter(deploymentCount, 0, 127, 2000);
    }
    
    if (visitorCount) {
        const currentCount = parseInt(localStorage.getItem('visitorCount') || '0');
        animateCounter(visitorCount, 0, currentCount, 1500);
    }
}

/**
 * Animate a single counter
 * @param {HTMLElement} element - The counter element
 * @param {number} start - Start value
 * @param {number} end - End value
 * @param {number} duration - Animation duration in milliseconds
 */
function animateCounter(element, start, end, duration) {
    const startTime = performance.now();
    
    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Ease out animation
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + (end - start) * easeOut);
        
        element.textContent = current;
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    }
    
    requestAnimationFrame(updateCounter);
}

/**
 * Increment a counter and store in localStorage
 * @param {string} counterId - The counter element ID
 */
function incrementCounter(counterId) {
    const key = counterId;
    const currentValue = parseInt(localStorage.getItem(key) || '0');
    const newValue = currentValue + 1;
    
    localStorage.setItem(key, newValue.toString());
    
    const element = document.getElementById(counterId);
    if (element) {
        element.textContent = newValue;
    }
}

/**
 * Detect and display the current environment
 */
function detectEnvironment() {
    const hostname = window.location.hostname;
    const environmentSpan = document.getElementById('environment');
    
    if (!environmentSpan) return;
    
    let environment = 'Production';
    
    if (hostname.includes('preview')) {
        environment = 'Preview';
    } else if (hostname.includes('staging') || hostname.includes('test')) {
        environment = 'Staging';
    } else if (hostname.includes('localhost') || hostname.includes('127.0.0.1')) {
        environment = 'Development';
    }
    
    environmentSpan.textContent = environment;
    console.log(`Running in ${environment} environment`);
}

/**
 * Log deployment information
 */
function logDeploymentInfo() {
    const deploymentInfo = {
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight
        },
        location: window.location.href
    };
    
    console.log('Deployment Info:', deploymentInfo);
}

/**
 * Show notification (utility function)
 * @param {string} message - The notification message
 * @param {string} type - The notification type
 */
function showNotification(message, type) {
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#10b981' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Utility function to get query parameters
 * @param {string} param - The parameter name
 * @returns {string|null} - The parameter value or null
 */
function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Utility function to format date
 * @param {Date} date - The date to format
 * @returns {string} - Formatted date string
 */
function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}
