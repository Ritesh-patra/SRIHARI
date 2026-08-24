/**
 * Resumable chunked uploader for SEAS (Report Analysis + Reading Upload).
 *
 * The browser slices the File and posts one small part per request, so a 300 MB
 * file never has to fit inside PHP's upload_max_filesize / post_max_size — only a
 * single chunk does. Plain fetch + Alpine, no extra dependencies.
 *
 * Blade usage:
 *   x-data="chunkedUpload({ initUrl, partUrl, completeUrl, statusUrl, abortUrl,
 *                           purpose, meta, chunkSize, maxBytes, csrf })"
 * where the part/complete/status/abort URLs contain the literal token __UUID__.
 */

const MAX_PART_ATTEMPTS = 4;
const RETRY_BACKOFF_MS = [600, 1800, 4000];
const PARSE_POLL_MS = 2000;
const PARSE_POLL_MAX_TICKS = 900; // ~30 minutes

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(1)} MB`;
    return `${(bytes / 1073741824).toFixed(2)} GB`;
}

function formatDuration(seconds) {
    if (!isFinite(seconds) || seconds < 0) return '—';
    if (seconds < 60) return `${Math.max(1, Math.round(seconds))}s`;
    const mins = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    if (mins < 60) return `${mins}m ${secs}s`;
    return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export function chunkedUpload(config = {}) {
    return {
        cfg: {
            chunkSize: 1048576,
            maxBytes: 1024 * 1024 * 1024,
            accept: ['csv', 'txt', 'xlsx', 'xls'],
            autoStart: true,
            pollParse: true,
            reloadOnFinish: true,
            ...config,
        },

        file: null,
        fileName: '',
        uuid: null,
        phase: 'idle', // idle | uploading | merging | parsing | done | error | cancelled
        percent: 0,
        uploadedBytes: 0,
        totalBytes: 0,
        message: '',
        error: '',
        speedBytesPerSecond: 0,
        etaSeconds: null,
        controller: null,
        cancelRequested: false,
        pollTicks: 0,

        get busy() {
            return ['uploading', 'merging', 'parsing'].includes(this.phase);
        },

        get uploadedLabel() {
            return formatBytes(this.uploadedBytes);
        },

        get totalLabel() {
            return formatBytes(this.totalBytes);
        },

        get speedLabel() {
            if (!this.speedBytesPerSecond) return '—';
            return `${formatBytes(this.speedBytesPerSecond)}/s`;
        },

        get etaLabel() {
            if (this.etaSeconds === null) return '—';
            return formatDuration(this.etaSeconds);
        },

        get phaseLabel() {
            return {
                idle: 'Ready',
                uploading: 'Uploading',
                merging: 'Finalising',
                parsing: 'Reading file',
                done: 'Done',
                error: 'Failed',
                cancelled: 'Cancelled',
            }[this.phase] || this.phase;
        },

        pick(event) {
            const file = event?.target?.files?.[0] || null;
            this.reset();
            if (!file) return;

            const extension = (file.name.split('.').pop() || '').toLowerCase();
            if (this.cfg.accept.length && !this.cfg.accept.includes(extension)) {
                this.error = `Only ${this.cfg.accept.map((e) => `.${e}`).join(', ')} files are accepted.`;
                this.phase = 'error';
                return;
            }
            if (file.size > this.cfg.maxBytes) {
                this.error = `File is ${formatBytes(file.size)} — the limit is ${formatBytes(this.cfg.maxBytes)}.`;
                this.phase = 'error';
                return;
            }
            if (file.size < 1) {
                this.error = 'That file is empty.';
                this.phase = 'error';
                return;
            }

            this.file = file;
            this.fileName = file.name;
            this.totalBytes = file.size;

            if (this.cfg.autoStart) {
                this.start();
            }
        },

        reset() {
            this.file = null;
            this.fileName = '';
            this.uuid = null;
            this.phase = 'idle';
            this.percent = 0;
            this.uploadedBytes = 0;
            this.totalBytes = 0;
            this.message = '';
            this.error = '';
            this.speedBytesPerSecond = 0;
            this.etaSeconds = null;
            this.cancelRequested = false;
            this.pollTicks = 0;
            this.controller = null;
        },

        async start() {
            if (!this.file || this.busy) return;

            this.error = '';
            this.message = '';
            this.cancelRequested = false;
            this.phase = 'uploading';
            this.controller = new AbortController();

            try {
                const session = await this.initSession();
                this.uuid = session.uuid;

                const chunkSize = session.chunk_size || this.cfg.chunkSize;
                const totalChunks = session.total_chunks;
                const received = new Set(session.received || []);

                this.uploadedBytes = Math.min(this.totalBytes, received.size * chunkSize);
                this.percent = Math.round((received.size / totalChunks) * 100);
                if (received.size > 0) {
                    this.message = `Resuming — ${received.size} of ${totalChunks} parts already uploaded.`;
                }

                const startedAt = Date.now();
                let bytesThisSession = 0;

                for (let index = 0; index < totalChunks; index++) {
                    if (this.cancelRequested) {
                        this.phase = 'cancelled';
                        return;
                    }
                    if (received.has(index)) continue;

                    const from = index * chunkSize;
                    const to = Math.min(this.totalBytes, from + chunkSize);
                    const blob = this.file.slice(from, to);

                    await this.sendPart(index, blob);

                    bytesThisSession += to - from;
                    this.uploadedBytes = Math.min(this.totalBytes, this.uploadedBytes + (to - from));
                    this.percent = Math.min(99, Math.round((this.uploadedBytes / this.totalBytes) * 100));

                    const elapsed = (Date.now() - startedAt) / 1000;
                    if (elapsed > 0.5) {
                        this.speedBytesPerSecond = bytesThisSession / elapsed;
                        const remaining = this.totalBytes - this.uploadedBytes;
                        this.etaSeconds = this.speedBytesPerSecond > 0 ? remaining / this.speedBytesPerSecond : null;
                    }
                }

                if (this.cancelRequested) {
                    this.phase = 'cancelled';
                    return;
                }

                this.phase = 'merging';
                this.message = 'Joining parts on the server…';
                this.etaSeconds = null;

                const result = await this.completeSession();

                this.percent = 100;
                this.uploadedBytes = this.totalBytes;
                this.message = result.message || 'Upload complete.';

                if (this.cfg.pollParse) {
                    this.phase = 'parsing';
                    await this.pollParseStatus();
                } else {
                    this.phase = 'done';
                }

                if (this.phase === 'done' && this.cfg.reloadOnFinish) {
                    window.location.reload();
                }
            } catch (e) {
                if (this.cancelRequested) {
                    this.phase = 'cancelled';
                    return;
                }
                this.phase = 'error';
                this.error = e?.message || 'Upload failed.';
            } finally {
                this.controller = null;
            }
        },

        async cancel() {
            this.cancelRequested = true;
            if (this.controller) {
                this.controller.abort();
            }
            if (this.uuid) {
                try {
                    await fetch(this.url(this.cfg.abortUrl), {
                        method: 'DELETE',
                        headers: this.headers(),
                        credentials: 'same-origin',
                    });
                } catch (e) {
                    // Server-side cleanup also runs from seas:cleanup-chunks.
                }
            }
            this.phase = 'cancelled';
            this.message = 'Upload cancelled.';
        },

        async initSession() {
            const meta = typeof this.cfg.meta === 'function' ? this.cfg.meta() : this.cfg.meta || {};

            const response = await fetch(this.cfg.initUrl, {
                method: 'POST',
                headers: { ...this.headers(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                signal: this.controller?.signal,
                body: JSON.stringify({
                    purpose: this.cfg.purpose,
                    original_name: this.file.name,
                    total_size: this.file.size,
                    chunk_size: this.cfg.chunkSize,
                    mime: this.file.type || null,
                    meta,
                }),
            });

            return this.readJson(response, 'Could not start the upload.');
        },

        async sendPart(index, blob) {
            let lastError = null;

            for (let attempt = 0; attempt < MAX_PART_ATTEMPTS; attempt++) {
                if (this.cancelRequested) throw new Error('cancelled');

                try {
                    const body = new FormData();
                    body.append('index', String(index));
                    body.append('chunk', blob, `part_${index}`);

                    const response = await fetch(this.url(this.cfg.partUrl), {
                        method: 'POST',
                        headers: this.headers(),
                        credentials: 'same-origin',
                        signal: this.controller?.signal,
                        body,
                    });

                    await this.readJson(response, `Chunk ${index + 1} was rejected.`);
                    return;
                } catch (e) {
                    if (this.cancelRequested) throw e;
                    lastError = e;
                    if (attempt < MAX_PART_ATTEMPTS - 1) {
                        this.message = `Retrying part ${index + 1}…`;
                        await sleep(RETRY_BACKOFF_MS[Math.min(attempt, RETRY_BACKOFF_MS.length - 1)]);
                    }
                }
            }

            throw lastError || new Error(`Chunk ${index + 1} failed after ${MAX_PART_ATTEMPTS} attempts.`);
        },

        async completeSession() {
            const response = await fetch(this.url(this.cfg.completeUrl), {
                method: 'POST',
                headers: { ...this.headers(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: '{}',
            });

            return this.readJson(response, 'Could not finalise the upload.');
        },

        async pollParseStatus() {
            this.pollTicks = 0;

            while (this.pollTicks < PARSE_POLL_MAX_TICKS) {
                this.pollTicks++;
                await sleep(PARSE_POLL_MS);

                let payload;
                try {
                    const response = await fetch(this.url(this.cfg.statusUrl), {
                        method: 'GET',
                        headers: this.headers(),
                        credentials: 'same-origin',
                    });
                    payload = await this.readJson(response, 'Could not read the parse status.');
                } catch (e) {
                    continue; // transient — keep polling
                }

                const domain = payload.domain;
                if (!domain) {
                    this.phase = 'done';
                    return;
                }

                if (domain.rows_imported !== undefined && domain.rows_imported !== null) {
                    this.message = `Imported ${Number(domain.rows_imported).toLocaleString()} rows…`;
                }

                if (domain.status === 'completed') {
                    this.phase = 'done';
                    this.message = domain.rows_imported !== undefined && domain.rows_imported !== null
                        ? `Imported ${Number(domain.rows_imported).toLocaleString()} rows.`
                        : 'File read successfully.';
                    return;
                }

                if (domain.status === 'failed') {
                    this.phase = 'error';
                    this.error = domain.error || 'The file could not be read.';
                    return;
                }
            }

            this.phase = 'done';
            this.message = 'Still processing in the background — refresh in a few minutes.';
        },

        url(template) {
            return String(template || '').replace('__UUID__', this.uuid || '');
        },

        headers() {
            return {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },

        async readJson(response, fallbackMessage) {
            let payload = null;
            try {
                payload = await response.json();
            } catch (e) {
                payload = null;
            }

            if (response.ok) {
                return payload || {};
            }

            if (response.status === 419) {
                throw new Error('Your session expired — please refresh the page and try again.');
            }

            const validation = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(validation || payload?.message || `${fallbackMessage} (HTTP ${response.status})`);
        },
    };
}

export function registerChunkedUpload(Alpine) {
    Alpine.data('chunkedUpload', chunkedUpload);
    window.chunkedUpload = chunkedUpload;
}
