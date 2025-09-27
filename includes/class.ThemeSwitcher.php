<?

class ThemeSwitcher{
	
	public function __toString(): string
    {
		global $javas,$nframework;
		if (! $nframework->onces['ThemeSwitcher']) {
		$javas->addjs(<<<ll
			const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    prefersDark.addEventListener('change', () => {
        if (localStorage.getItem('user-theme') === 'system') applyTheme('system');
    });
    const storedTheme = localStorage.getItem('user-theme') || 'system';
    const applyTheme = theme => {
        var d=( theme === 'system' ? (prefersDark.matches ? 'dark' : 'light') : theme);
        if(d=='dark'){
        	document.documentElement.classList.add('dark-side');
        }else{
        	document.documentElement.classList.remove('dark-side');
        }
        document.querySelector('#theme-switcher')?.setAttribute('data-mode', theme);
    };
    applyTheme(storedTheme);
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.theme-switcher div').forEach(btn =>
            btn.addEventListener('click', () => {
                localStorage.setItem('user-theme', btn.dataset.theme);
                applyTheme(btn.dataset.theme);
            })
        );
        applyTheme(storedTheme);
    });
ll
);
$nframework->onces['ThemeSwitcher']=true;
}
		
		return '<div class="theme-switcher" id="theme-switcher">
            <div class="button flat" data-tooltip="Light theme" data-theme="light">
                <span class="mif-sunny icon"></span>
            </div>
            <div class="button flat" data-tooltip="OS System" data-theme="system">
                <span class="mif-contrast icon"></span>
            </div>
            <div class="button flat" data-tooltip="Dark theme" data-theme="dark">
                <span class="mif-moon-right icon"></span>
            </div>
            <span class="switch-handle"></span>
        </div>';

	}
}
