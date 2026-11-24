    </main>

    <!-- Admin Footer Selaras dengan Page User -->
    <footer class="text-center py-4 small text-muted">
        NISAYA · Catat, pahami, dan kenali siklusmu. (Administrator Panel)
    </footer>

    <!-- Bootstrap 5 Bundle JS & App Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/app.js"></script>
    <script>
        // Auto dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(al => {
                const bsAlert = bootstrap.Alert.getInstance(al) || new bootstrap.Alert(al);
                if (bsAlert) bsAlert.close();
            });
        }, 5000);
    </script>
    </body>

    </html>