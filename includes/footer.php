</main>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Keluar</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Tutup"></button>
            </div>

            <div class="modal-body text-center py-4">
                <div class="fs-1 mb-3">👋</div>
                <h6>Yakin ingin keluar dari NISAYA?</h6>
                <p class="text-muted small mb-0">
                    Kamu harus login kembali untuk mengakses akun.
                </p>
            </div>

            <div class="modal-footer justify-content-center">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal">
                    Batal
                </button>

                <form action="../auth/logout.php" method="POST">
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>">

                    <button type="submit" class="btn btn-danger">
                        Ya, Keluar
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<footer class="text-center py-4 small text-muted">
    NISAYA · Catat, pahami, dan kenali siklusmu.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/app.js"></script>

</body>

</html>