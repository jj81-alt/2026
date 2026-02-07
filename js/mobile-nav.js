// js/mobile-nav.js
// Mobile Navigation Toggle

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            const icon = this.querySelector('i');
            if (mobileMenu.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // Admin sidebar toggle
    const adminToggle = document.getElementById('admin-sidebar-toggle');
    const sidebar = document.querySelector('aside.w-64');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (adminToggle && sidebar) {
        adminToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            if (overlay) {
                overlay.classList.toggle('active');
            }
        });
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                this.classList.remove('active');
            });
        }
    }
});