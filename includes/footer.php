        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto no-print">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <span>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. All rights reserved.</span>
                <span>Role: <strong class="text-primary text-uppercase"><?= e($role ?? 'Guest') ?></strong> | System Version 1.0</span>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js CDN (for Analytics) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom App JS -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>

</body>
</html>
