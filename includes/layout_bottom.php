</main>
        <footer class="px-6 py-3 bg-sidebarbg border-t border-borderdark text-xs text-slate-500 flex flex-wrap justify-between gap-2">
            <span><?= e($settings['system_name']) ?> &copy; <?= date('Y') ?></span>
            <span>Đồ án 3 - Khoa CNTT (Đoàn Thanh Tân &amp; Ngô Minh Phúc)</span>
        </footer>
    </div>
</div>

<script>
function updateClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleString('vi-VN');
    }
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>