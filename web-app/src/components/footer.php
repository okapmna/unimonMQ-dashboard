    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>
    <?php if (isset($_SESSION['toast'])): ?>
        <?php
        $toast = $_SESSION['toast'];
        $toastType = $toast['type'] === 'success' ? 'bg-accent-green' : 'bg-red-500';
        $toastIcon = $toast['type'] === 'success' 
            ? '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        ?>
        <div id="toast-message" class="fixed top-5 left-1/2 z-50 transform -translate-x-1/2 -translate-y-24 opacity-0 transition-all duration-300 flex items-center p-4 mb-4 text-white rounded-xl shadow-lg min-w-[300px] <?= $toastType ?>" role="alert">
            <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg bg-white/20">
                <?= $toastIcon ?>
            </div>
            <div class="ml-3 text-sm font-semibold tracking-wide mr-4 w-full text-center"><?= htmlspecialchars($toast['message']) ?></div>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white/10 text-white rounded-lg focus:ring-2 focus:ring-white p-1.5 hover:bg-white/20 inline-flex items-center justify-center h-8 w-8 transition-colors" onclick="const t = document.getElementById('toast-message'); t.classList.remove('translate-y-0', 'opacity-100'); t.classList.add('-translate-y-24', 'opacity-0'); setTimeout(() => t.remove(), 300)" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                </svg>
            </button>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const toast = document.getElementById('toast-message');
                if(toast) {
                    setTimeout(() => {
                        toast.classList.remove('-translate-y-24', 'opacity-0');
                        toast.classList.add('translate-y-0', 'opacity-100');
                    }, 100);
                    setTimeout(() => {
                        toast.classList.remove('translate-y-0', 'opacity-100');
                        toast.classList.add('-translate-y-24', 'opacity-0');
                        setTimeout(() => toast.remove(), 300);
                    }, 4000);
                }
            });
        </script>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
