<script>
    (() => {
        let savedTheme;
        try { savedTheme = localStorage.getItem('clinic-theme'); } catch {}
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', savedTheme === 'dark' || (savedTheme !== 'light' && prefersDark));
    })();
</script>
