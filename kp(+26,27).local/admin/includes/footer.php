        </main>
    </div>

    <!-- ПОДВАЛ -->
    <footer class="admin-footer">
        <p>© 2025 ARTOBJECT Gallery. Все права защищены.</p>
    </footer>

    <script>
        // Мобильное меню
        function toggleMobileMenu() {
            document.querySelector('.burger-menu').classList.toggle('active');
            document.getElementById('mobileMenu').classList.toggle('active');
            document.getElementById('mobileMenuOverlay').classList.toggle('active');
            document.body.classList.toggle('no-scroll');
        }

        function closeMobileMenu() {
            document.querySelector('.burger-menu').classList.remove('active');
            document.getElementById('mobileMenu').classList.remove('active');
            document.getElementById('mobileMenuOverlay').classList.remove('active');
            document.body.classList.remove('no-scroll');
        }

        // Закрытие по клику вне меню
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const burger = document.querySelector('.burger-menu');
            
            if (!mobileMenu.contains(event.target) && !burger.contains(event.target)) {
                closeMobileMenu();
            }
        });

        // Закрытие при ресайзе
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>