@once
    <script>
        localStorage.setItem('theme', 'light')
        document.documentElement.classList.remove('dark')

        document.addEventListener('alpine:initialized', () => {
            const sidebar = window.Alpine?.store('sidebar')
            if (! sidebar) return

            const key = 'sivmi.sidebar.collapsed'
            const savedState = localStorage.getItem(key)

            if ((savedState !== null) && (window.innerWidth >= 1024)) {
                savedState === 'true' ? sidebar.close() : sidebar.open()
            }

            window.Alpine.effect(() => {
                if (window.innerWidth >= 1024) {
                    localStorage.setItem(key, String(! sidebar.isOpenDesktop))
                }
            })
        })
    </script>
@endonce
