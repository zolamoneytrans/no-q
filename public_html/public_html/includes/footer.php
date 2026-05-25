    <footer class="app-footer">
        <p>&copy; <?= date('Y'); ?> No Q. All rights reserved</p>
        <p>Powered by <a href="https://www.jaekerna.com/" target="_blank" style="color:#1e3c72;">Jaekerna Investments</a></p>
    </footer>

    <script>
    // Smooth scroll function (only needed if About link exists)
    function smoothScrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) return;
        const targetPosition = section.getBoundingClientRect().top + window.pageYOffset;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        const duration = 700;
        let startTime = null;
        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);
            const ease = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            window.scrollTo(0, startPosition + distance * ease);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }
        requestAnimationFrame(animation);
    }

    // Hamburger menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('show');
            });
        }
        // Close menu when a link is clicked
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (navLinks) navLinks.classList.remove('show');
            });
        });
    });
    </script>
</body>
</html>
