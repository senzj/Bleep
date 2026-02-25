export default function commentSheet() {
    return {
        open: false,
        bleepId: null,
        isMobile: window.innerWidth < 1024,
        sheetY: 0,
        startY: 0,
        dragging: false,
        sheetHeight: 0,
        fullscreen: false,

        init() {
            this.updateSize();
            this._onResize = () => this.updateSize();
            window.addEventListener('resize', this._onResize);

            this._onMouseMove = (e) => this.onDrag(e, false);
            this._onMouseUp = () => this.endDrag();
            document.addEventListener('mousemove', this._onMouseMove);
            document.addEventListener('mouseup', this._onMouseUp);

            this._onTouchMove = (e) => {
                if (!this.dragging) return;
                e.preventDefault();
                this.onDrag(e, true);
            };
            this._onTouchEnd = () => this.endDrag();
            document.addEventListener('touchmove', this._onTouchMove, { passive: false });
            document.addEventListener('touchend', this._onTouchEnd);

            this.$watch('open', value => {
                document.body.style.overflow = value ? 'hidden' : '';
                if (!value) this.sheetY = 0;
            });

            this._onCloseRequest = () => this.close();
            window.addEventListener('request-close-comments', this._onCloseRequest);
        },

        destroy() {
            window.removeEventListener('resize', this._onResize);
            window.removeEventListener('request-close-comments', this._onCloseRequest);
            document.removeEventListener('mousemove', this._onMouseMove);
            document.removeEventListener('mouseup', this._onMouseUp);
            document.removeEventListener('touchmove', this._onTouchMove);
            document.removeEventListener('touchend', this._onTouchEnd);
            document.body.style.overflow = '';
        },

        updateSize() {
            const wasMobile  = this.isMobile;
            this.isMobile    = window.innerWidth < 1024;
            this.sheetHeight = Math.round(window.innerHeight * 0.85);

            // Breakpoint crossed while open — close immediately (no animation)
            // so the modal doesn't ghost-appear in the wrong layout.
            if (wasMobile !== this.isMobile && this.open) {
                this.open       = false;
                this.sheetY     = 0;
                this.fullscreen = false;
                document.body.style.overflow = '';
                window.dispatchEvent(new CustomEvent('close-comments'));
            }
        },

        startDrag(e) {
            this.dragging = true;
            this.startY = e.touches ? e.touches[0].clientY : e.clientY;
        },

        onDrag(e, isTouch = true) {
            if (!this.dragging) return;
            const clientY = isTouch ? e.touches[0].clientY : e.clientY;
            const deltaY = clientY - this.startY;

            if (deltaY < -60 && !this.fullscreen) {
                this.fullscreen = true;
                this.startY = clientY;
                this.sheetY = 0;
                return;
            }

            if (this.fullscreen && deltaY > 60) {
                this.fullscreen = false;
                this.startY = clientY;
                this.sheetY = 0;
                return;
            }

            if (!this.fullscreen) {
                this.sheetY = deltaY < 0
                    ? deltaY * 0.15  // rubber band upward
                    : Math.min(this.sheetHeight, deltaY);
            }
        },

        endDrag() {
            if (!this.dragging) return;
            this.dragging = false;

            if (!this.fullscreen && this.sheetY > this.sheetHeight * 0.35) {
                this.close();
                return;
            }

            this.sheetY = 0;
        },

        openSheet(id) {
            this.fullscreen = false;
            this.sheetY = 0;
            this.bleepId = id;
            this.open = true;

            // Show the spinner when the sheet opens (it's hidden by default in
            // modal mode to prevent ghost flash on resize).
            const spinner = document.querySelector(
                '#comments-container-layout-modal [data-comment-spinner]'
            );
            if (spinner) spinner.style.display = '';
        },

        close() {
            if (!this.open) return;
            this.sheetY = this.sheetHeight;
            setTimeout(() => {
                this.open = false;
                this.sheetY = 0;
                this.fullscreen = false;
                document.body.style.overflow = '';
                window.dispatchEvent(new CustomEvent('close-comments'));
            }, 280);
        },

        get mobileStyle() {
            // Hard-hide during breakpoint transition — same guard as desktopStyle.
            if (!this.open) {
                return 'display:none';
            }

            const transition = this.dragging
                ? 'none'
                : 'transform 0.28s cubic-bezier(0.32, 0.72, 0, 1)';

            if (this.fullscreen) {
                return [
                    'position:fixed',
                    'height:100dvh',
                    'width:100vw',
                    'top:0','left:0','right:0','bottom:auto',
                    'transform:translateY(0px)',
                    `transition:${transition}`,
                ].join(';');
            }

            return [
                'position:fixed',
                `height:min(${this.sheetHeight}px, 100dvh)`,
                'width:100vw',
                `transform:translateY(${this.sheetY}px)`,
                `transition:${transition}`,
            ].join(';');
        },

        get desktopStyle() {
            if (!this.open) {
                return 'display:none';
            }
            return [
                'width:min(35vw, 420px)',
                'max-width:calc(100vw - 2rem)',
                'height:min(85vh, 90dvh)',
            ].join(';');
        },
    };
}
