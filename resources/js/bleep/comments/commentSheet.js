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

            // Touch move needs passive: false to allow preventDefault
            this._onTouchMove = (e) => {
                if (!this.dragging) return;
                e.preventDefault(); // only called when actually dragging
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
            this.isMobile = window.innerWidth < 1024;
            this.sheetHeight = Math.round(window.innerHeight * 0.85);
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
                // Drag up past threshold — go fullscreen smoothly
                this.fullscreen = true;
                this.startY = clientY; // reset so it doesnt snap
                this.sheetY = 0;
                return;
            }

            if (this.fullscreen && deltaY > 60) {
                // Drag down from fullscreen — exit fullscreen smoothly
                this.fullscreen = false;
                this.startY = clientY;
                this.sheetY = 0;
                return;
            }

            if (!this.fullscreen) {
                // Rubber band effect when dragging up past 0
                if (deltaY < 0) {
                    // Allow slight upward drag with resistance
                    this.sheetY = deltaY * 0.15;
                } else {
                    this.sheetY = Math.min(this.sheetHeight, deltaY);
                }
            }
        },

        endDrag() {
            if (!this.dragging) return;
            this.dragging = false;

            if (!this.fullscreen && this.sheetY > this.sheetHeight * 0.35) {
                this.close();
                return;
            }

            // Snap back
            this.sheetY = 0;
        },

        openSheet(id) {
            this.fullscreen = false;
            this.sheetY = 0;
            this.bleepId = id;
            this.open = true;
        },

        close() {
            if (!this.open) return;
            // Animate out first, then fully close
            this.sheetY = this.sheetHeight;
            setTimeout(() => {
                this.open = false;
                this.sheetY = 0;
                this.fullscreen = false;
                document.body.style.overflow = '';
                window.dispatchEvent(new CustomEvent('close-comments'));
            }, 280); // match transition duration
        },

        get mobileStyle() {
            const transition = this.dragging
                ? 'none'  // no transition while dragging — feels immediate
                : 'transform 0.28s cubic-bezier(0.32, 0.72, 0, 1)';

            if (this.fullscreen) {
                return `
                    position: fixed;
                    height: 100dvh;
                    width: 100vw;
                    top: 0; left: 0; right: 0; bottom: auto;
                    transform: translateY(0px);
                    transition: ${transition};
                `;
            }

            return `
                position: fixed;
                height: min(${this.sheetHeight}px, 100dvh);
                width: 100vw;
                transform: translateY(${this.open ? this.sheetY : this.sheetHeight}px);
                transition: ${transition};
            `;
        },

        get desktopStyle() {
            return `
                width: min(35vw, 420px);
                max-width: calc(100vw - 2rem);
                height: min(85vh, 90dvh);
            `;
        }
    };
}
