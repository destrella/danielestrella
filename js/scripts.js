/**
 * Live Photo Manager
 * Maneja múltiples Live Photos en la página
 */
class LivePhotoManager {
    constructor() {
        this.livePhotos = [];
        this.init();
    }

    init() {
        // Buscar todos los contenedores con la clase 'live-photo'
        const containers = document.querySelectorAll('.contenedor-imagen.live-photo');

        containers.forEach((container, index) => {
            // Crear una instancia para cada Live Photo
            const livePhoto = new LivePhoto(container, index);
            this.livePhotos.push(livePhoto);
        });

        console.log(`Inicializadas ${this.livePhotos.length} Live Photos`);
    }

    // Método para pausar todos los videos (útil para cambiar de página)
    pauseAll() {
        this.livePhotos.forEach(photo => photo.stop());
    }

    // Método para reiniciar todos los videos
    resetAll() {
        this.livePhotos.forEach(photo => photo.reset());
    }
}

/**
 * Clase Live Photo individual
 */
class LivePhoto {
    constructor(container, id) {
        this.container = container;
        this.id = id || Math.random().toString(36).substr(2, 9);

        // Encontrar los elementos dentro de este contenedor
        this.image = container.querySelector('.foto-estatica');
        this.video = container.querySelector('.live-video');
        this.badge = container.querySelector('.live-badge');

        this.isPlaying = false;
        this.isHovering = false;
        this.videoReady = false;

        this.init();
    }

    init() {
        if (!this.video || !this.image) {
            console.warn('Live Photo: Faltan elementos requeridos', this.container);
            return;
        }

        // Configurar el video
        this.video.setAttribute('preload', 'metadata');

        // Estado de carga
        this.container.classList.add('loading');

        // Esperar a que el video esté listo
        this.video.addEventListener('loadeddata', () => {
            this.videoReady = true;
            this.container.classList.remove('loading');
        });

        // Manejar errores de video
        this.video.addEventListener('error', (e) => {
            console.warn('Error cargando video:', e);
            this.container.classList.remove('loading');
            this.badge.style.opacity = '0.5';
        });

        // Precargar el video (sin reproducir)
        this.video.load();

        // Event listeners
        this.container.addEventListener('mouseenter', () => this.onMouseEnter());
        this.container.addEventListener('mouseleave', () => this.onMouseLeave());
        this.container.addEventListener('touchstart', (e) => this.onTouchStart(e));
        this.container.addEventListener('touchend', (e) => this.onTouchEnd(e));

        // Prevenir que el video capture eventos
        this.video.addEventListener('click', (e) => e.stopPropagation());
        this.video.addEventListener('mouseenter', (e) => e.stopPropagation());

        // Para dispositivos que prefieren reducir movimiento
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.container.classList.add('reduced-motion');
        }
    }

    onMouseEnter() {
        this.isHovering = true;
        this.play();
    }

    onMouseLeave() {
        this.isHovering = false;
        this.stop();
    }

    onTouchStart(e) {
        e.preventDefault();

        if (!this.isPlaying) {
            // Al tocar, reproducimos
            this.play();

            // Auto-detener después de un ciclo (opcional)
            if (this.video.loop) {
                this.video.loop = false;
                this.video.addEventListener('ended', () => {
                    this.stop();
                    this.video.loop = true; // Restaurar loop
                }, { once: true });
            }
        } else {
            // Si ya está reproduciendo, detenemos
            this.stop();
        }
    }

    onTouchEnd(e) {
        e.preventDefault();
        // Podemos añadir lógica extra si es necesario
    }

    async play() {
        if (this.isPlaying || !this.videoReady) return;

        try {
            // Asegurar que el video empieza desde el principio
            this.video.currentTime = 0;

            // Intentar reproducir
            await this.video.play();
            this.isPlaying = true;

            // Añadir clase para estilos adicionales
            this.container.classList.add('playing');

        } catch (error) {
            console.log('No se pudo reproducir el video:', error);

            // Fallback: si el error es por interacción, guardamos el intento
            if (error.name === 'NotAllowedError') {
                // Esperar a interacción del usuario
                const playOnInteraction = () => {
                    this.video.play()
                        .then(() => {
                            this.isPlaying = true;
                            this.container.classList.add('playing');
                        })
                        .catch(e => console.log('Error en fallback:', e));

                    document.removeEventListener('click', playOnInteraction);
                    document.removeEventListener('touchstart', playOnInteraction);
                };

                document.addEventListener('click', playOnInteraction, { once: true });
                document.addEventListener('touchstart', playOnInteraction, { once: true });
            }
        }
    }

    stop() {
        if (!this.isPlaying) return;

        this.video.pause();
        this.video.currentTime = 0;
        this.isPlaying = false;
        this.container.classList.remove('playing');
    }

    reset() {
        this.stop();
        this.video.currentTime = 0;
    }

    // Método para destruir la instancia (limpieza)
    destroy() {
        this.stop();
        this.video = null;
        this.image = null;
        this.badge = null;
        this.container = null;
    }
}

/**
 * Lightbox sencillo para galerías de imágenes y videos.
 */
class GalleryLightbox {
    constructor() {
        this.galleries = [];
        this.currentGallery = null;
        this.currentIndex = 0;
        this.isOpen = false;
        this.lastFocused = null;
        this.init();
    }

    init() {
        const galleryElements = document.querySelectorAll('.galeria-imagenes, [data-lightbox-gallery]');

        if (!galleryElements.length) return;

        this.buildLightbox();

        galleryElements.forEach(gallery => {
            const items = this.getItems(gallery);

            if (!items.length) return;

            const galleryData = { gallery, items };
            this.galleries.push(galleryData);

            items.forEach((item, index) => this.prepareTrigger(item, galleryData, index));
        });

        document.addEventListener('keydown', event => this.onKeydown(event));
    }

    getItems(gallery) {
        const usedMedia = new Set();
        const items = [];

        gallery.querySelectorAll('figure').forEach(figure => {
            const media = figure.querySelector('img, video');

            if (!media) return;

            usedMedia.add(media);
            items.push(this.createItem(media, figure));
        });

        gallery.querySelectorAll('img, video').forEach(media => {
            if (usedMedia.has(media)) return;
            items.push(this.createItem(media, null));
        });

        return items;
    }

    createItem(media, figure) {
        const isVideo = media.tagName === 'VIDEO';
        const link = media.closest('a[href]');
        const captionElement = figure?.querySelector('figcaption');
        const caption = media.dataset.caption || captionElement?.textContent.trim() || media.getAttribute('aria-label') || media.alt || '';
        const source = isVideo ? media.querySelector('source') : null;

        return {
            type: isVideo ? 'video' : 'image',
            media,
            link,
            trigger: link || media,
            get src() {
                if (isVideo) {
                    return media.dataset.full || source?.src || media.currentSrc || media.src || link?.href;
                }

                return media.dataset.full || link?.href || media.currentSrc || media.src;
            },
            poster: isVideo ? media.getAttribute('poster') || '' : '',
            sourceType: isVideo ? source?.type || '' : '',
            alt: media.alt || media.getAttribute('aria-label') || caption,
            caption
        };
    }

    prepareTrigger(item, galleryData, index) {
        const { trigger } = item;
        const label = `Abrir ${item.type === 'video' ? 'video' : 'imagen'} ${index + 1} de ${galleryData.items.length}`;

        if (trigger.tagName === 'A') {
            trigger.classList.add('enlace-galeria');
            trigger.setAttribute('aria-label', label);
        } else {
            trigger.setAttribute('role', 'button');
            trigger.setAttribute('tabindex', '0');
            trigger.setAttribute('aria-label', label);
        }

        trigger.addEventListener('click', event => {
            event.preventDefault();
            this.open(galleryData, index);
        });

        trigger.addEventListener('keydown', event => {
            if (event.key !== ' ' && event.key !== 'Enter') return;

            event.preventDefault();
            this.open(galleryData, index);
        });
    }

    buildLightbox() {
        this.lightbox = document.createElement('div');
        this.lightbox.className = 'lightbox-galeria';
        this.lightbox.setAttribute('role', 'dialog');
        this.lightbox.setAttribute('aria-modal', 'true');
        this.lightbox.setAttribute('aria-label', 'Visor de galería');
        this.lightbox.hidden = true;
        this.lightbox.innerHTML = `
            <div class="lightbox-galeria__backdrop" data-lightbox-close></div>
            <div class="lightbox-galeria__panel">
                <button type="button" class="lightbox-galeria__boton lightbox-galeria__cerrar" data-lightbox-close aria-label="Cerrar galería">&times;</button>
                <p class="lightbox-galeria__contador" aria-live="polite"></p>
                <button type="button" class="lightbox-galeria__boton lightbox-galeria__anterior" aria-label="Elemento anterior">&#8249;</button>
                <img class="lightbox-galeria__imagen" alt="">
                <video class="lightbox-galeria__video" controls autoplay muted playsinline preload="metadata" hidden><source></video>
                <button type="button" class="lightbox-galeria__boton lightbox-galeria__siguiente" aria-label="Elemento siguiente">&#8250;</button>
                <p class="lightbox-galeria__caption"></p>
            </div>
        `;

        document.body.appendChild(this.lightbox);

        this.image = this.lightbox.querySelector('.lightbox-galeria__imagen');
        this.video = this.lightbox.querySelector('.lightbox-galeria__video');
        this.videoSource = this.video.querySelector('source');
        this.caption = this.lightbox.querySelector('.lightbox-galeria__caption');
        this.counter = this.lightbox.querySelector('.lightbox-galeria__contador');
        this.closeButton = this.lightbox.querySelector('.lightbox-galeria__cerrar');
        this.previousButton = this.lightbox.querySelector('.lightbox-galeria__anterior');
        this.nextButton = this.lightbox.querySelector('.lightbox-galeria__siguiente');

        this.lightbox.querySelectorAll('[data-lightbox-close]').forEach(button => {
            button.addEventListener('click', () => this.close());
        });

        this.previousButton.addEventListener('click', () => this.showPrevious());
        this.nextButton.addEventListener('click', () => this.showNext());
    }

    open(galleryData, index) {
        this.currentGallery = galleryData;
        this.currentIndex = index;
        this.isOpen = true;
        this.lastFocused = document.activeElement;

        this.lightbox.hidden = false;
        this.render();
        document.body.classList.add('lightbox-abierto');

        requestAnimationFrame(() => {
            this.lightbox.classList.add('is-open');
            this.closeButton.focus({ preventScroll: true });
        });
    }

    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        this.lightbox.classList.remove('is-open');
        document.body.classList.remove('lightbox-abierto');

        window.setTimeout(() => {
            if (this.isOpen) return;

            this.lightbox.hidden = true;
            this.resetMedia();
            this.caption.textContent = '';
        }, 180);

        this.lastFocused?.focus?.({ preventScroll: true });
    }

    render() {
        const item = this.currentGallery.items[this.currentIndex];
        const total = this.currentGallery.items.length;

        this.resetMedia();
        if (item.type === 'video') {
            this.image.hidden = true;
            this.video.hidden = false;
            this.videoSource.src = item.src;
            if (item.sourceType) {
                this.videoSource.type = item.sourceType;
            }
            if (item.poster) {
                this.video.poster = item.poster;
            }
            this.video.setAttribute('aria-label', item.alt || item.caption || 'Video de galería');
            this.video.muted = true;
            this.video.load();
            const playPromise = this.video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }
        } else {
            this.video.hidden = true;
            this.image.hidden = false;
            this.image.src = item.src;
            this.image.alt = item.alt;
        }
        this.caption.textContent = item.caption;
        this.caption.hidden = !item.caption;
        this.counter.textContent = `${this.currentIndex + 1} / ${total}`;
        this.counter.hidden = total < 2;
        this.previousButton.hidden = total < 2;
        this.nextButton.hidden = total < 2;
    }

    resetMedia() {
        this.image.removeAttribute('src');
        this.image.hidden = false;
        this.video.pause();
        this.video.removeAttribute('src');
        this.video.removeAttribute('poster');
        this.videoSource.removeAttribute('src');
        this.videoSource.removeAttribute('type');
        this.video.hidden = true;
        this.video.load();
    }

    showPrevious() {
        const total = this.currentGallery.items.length;
        this.currentIndex = (this.currentIndex - 1 + total) % total;
        this.render();
    }

    showNext() {
        const total = this.currentGallery.items.length;
        this.currentIndex = (this.currentIndex + 1) % total;
        this.render();
    }

    onKeydown(event) {
        if (!this.isOpen) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.showPrevious();
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.showNext();
            return;
        }

        if (event.key === 'Tab') {
            this.trapFocus(event);
        }
    }

    trapFocus(event) {
        const focusable = Array.from(this.lightbox.querySelectorAll('button:not([hidden]), video:not([hidden])'));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) return;

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

/**
 * Navegación con teclado para la paginación del archivo
 */
class ArchivePaginationKeyboard {
    constructor() {
        this.pagination = document.querySelector('.paginacion-archivo');

        if (!this.pagination) return;

        this.previousLink = this.findPaginationLink('anterior');
        this.nextLink = this.findPaginationLink('siguiente');

        if (!this.previousLink && !this.nextLink) return;

        document.addEventListener('keydown', event => this.onKeydown(event));
    }

    findPaginationLink(label) {
        const links = Array.from(this.pagination.querySelectorAll('.paginacion-archivo__saltos a[href]'));

        return links.find(link => link.textContent.trim().toLowerCase() === label) || null;
    }

    onKeydown(event) {
        if (event.defaultPrevented || this.hasModifierKey(event) || this.shouldIgnoreTarget(event.target)) return;

        if (event.key === 'ArrowLeft' && this.previousLink) {
            event.preventDefault();
            this.goTo(this.previousLink);
            return;
        }

        if (event.key === 'ArrowRight' && this.nextLink) {
            event.preventDefault();
            this.goTo(this.nextLink);
        }
    }

    hasModifierKey(event) {
        return event.altKey || event.ctrlKey || event.metaKey || event.shiftKey;
    }

    shouldIgnoreTarget(target) {
        if (!(target instanceof Element)) return false;

        return Boolean(target.closest('input, textarea, select, button, [contenteditable="true"], [role="textbox"]'));
    }

    goTo(link) {
        window.location.assign(link.href);
    }
}

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', () => {
    // Crear el manager global
    window.livePhotoManager = new LivePhotoManager();
    window.galleryLightbox = new GalleryLightbox();
    window.archivePaginationKeyboard = new ArchivePaginationKeyboard();
});

/**
 * Opcional: Mejorar la experiencia en dispositivos móviles
 */
function setupMobileOptimizations() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    if (isMobile) {
        // En móvil, cambiar el comportamiento para conservar batería
        document.querySelectorAll('.live-video').forEach(video => {
            video.setAttribute('preload', 'none');
        });

        // Reducir calidad de video en móvil (opcional)
        document.querySelectorAll('source').forEach(source => {
            if (source.src.includes('.mp4')) {
                // Podrías cambiar a una versión de menor calidad
                // source.src = source.src.replace('.mp4', '-mobile.mp4');
            }
        });
    }
}

// Llamar a la optimización móvil
setupMobileOptimizations();

/**
 * Intersection Observer para pausar videos fuera de la vista
 * (Ahorra batería y recursos)
 */
function setupIntersectionObserver() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const container = entry.target;
            const livePhoto = window.livePhotoManager?.livePhotos.find(
                lp => lp.container === container
            );

            if (!entry.isIntersecting && livePhoto?.isPlaying) {
                // Si el elemento no es visible y está reproduciendo, lo pausamos
                livePhoto.stop();
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    // Observar todos los contenedores
    document.querySelectorAll('.contenedor-imagen.live-photo').forEach(container => {
        observer.observe(container);
    });
}

// Configurar observer después de la inicialización
setTimeout(setupIntersectionObserver, 1000);

/**
 * Ejemplo de API pública para interactuar con las Live Photos
 */
window.LivePhotoAPI = {
    // Reproducir una Live Photo específica por índice
    playByIndex: (index) => {
        const photos = window.livePhotoManager?.livePhotos;
        if (photos && photos[index]) {
            photos[index].play();
        }
    },

    // Detener todas
    stopAll: () => {
        window.livePhotoManager?.pauseAll();
    },

    // Actualizar configuración
    updateConfig: (config) => {
        // Ejemplo: cambiar duración, loop, etc.
        document.querySelectorAll('.live-video').forEach(video => {
            if (config.loop !== undefined) video.loop = config.loop;
            if (config.muted !== undefined) video.muted = config.muted;
        });
    }
};
