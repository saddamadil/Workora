import Alpine from 'alpinejs';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Upload with per-file progress. Files are sent one at a time so a single
 * oversized or rejected file never fails the rest of the batch.
 */
Alpine.data('uploader', ({ url, maxMb }) => ({
    dragging: false,
    queue: [],
    busy: false,
    folder: '',

    pick(event) {
        this.add(event.target.files);
        event.target.value = '';
    },

    drop(event) {
        this.dragging = false;
        this.add(event.dataTransfer.files);
    },

    add(fileList) {
        for (const file of fileList) {
            const tooBig = file.size > maxMb * 1024 * 1024;
            this.queue.push({
                id: crypto.randomUUID(),
                file,
                name: file.name,
                size: file.size,
                progress: 0,
                state: tooBig ? 'error' : 'waiting',
                error: tooBig ? `Larger than ${maxMb} MB` : null,
            });
        }
        this.run();
    },

    async run() {
        if (this.busy) return;
        this.busy = true;

        for (const item of this.queue.filter((i) => i.state === 'waiting')) {
            await this.send(item);
        }

        this.busy = false;

        if (this.queue.some((i) => i.state === 'done')) {
            // Give the user a moment to see the green ticks, then show the new files.
            setTimeout(() => window.location.reload(), 900);
        }
    },

    send(item) {
        return new Promise((resolve) => {
            const body = new FormData();
            body.append('files[]', item.file);
            if (this.folder) body.append('folder', this.folder);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
            xhr.setRequestHeader('Accept', 'application/json');
            item.state = 'uploading';

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) item.progress = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload = () => {
                let data = {};
                try { data = JSON.parse(xhr.responseText); } catch (_) {}
                if (xhr.status >= 200 && xhr.status < 300) {
                    item.state = 'done';
                    item.progress = 100;
                } else {
                    item.state = 'error';
                    item.error = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || (xhr.status === 413 ? 'File is too large for the server' : 'Upload failed'));
                }
                resolve();
            };
            xhr.onerror = () => {
                item.state = 'error';
                item.error = 'Network problem. Check your connection.';
                resolve();
            };
            xhr.send(body);
        });
    },

    dismiss(id) {
        this.queue = this.queue.filter((i) => i.id !== id);
    },
}));

/** The share dialog: creates a link over fetch and shows it with a copy button. */
Alpine.data('shareDialog', () => ({
    open: false,
    file: null,
    url: null,
    busy: false,
    error: null,
    copied: false,
    form: { password: '', expires_in_days: '', max_downloads: '' },

    show(file) {
        this.file = file;
        this.url = null;
        this.error = null;
        this.copied = false;
        this.form = { password: '', expires_in_days: '', max_downloads: '' };
        this.open = true;
    },

    async create() {
        this.busy = true;
        this.error = null;
        try {
            const res = await fetch(this.file.shareUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(this.form),
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.errors ? Object.values(data.errors).flat()[0] : 'Could not create the link.';
            } else {
                this.url = data.url;
            }
        } catch (_) {
            this.error = 'Network problem. Please try again.';
        }
        this.busy = false;
    },

    async copy() {
        try {
            await navigator.clipboard.writeText(this.url);
        } catch (_) {
            this.$refs.link?.select();
            document.execCommand('copy');
        }
        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
    },
}));

window.Alpine = Alpine;
Alpine.start();
