import { Controller } from '@hotwired/stimulus'
import Sortable from 'sortablejs'

export default class extends Controller {
    static targets = ['input', 'list', 'empty', 'submit', 'dropzone']

    #files = new Map()
    #order = []
    #counter = 0
    #sortable = null

    connect() {
        this.#initSortable()
    }

    disconnect() {
        this.#sortable?.destroy()
    }

    onFileSelect(event) {
        this.#addFiles(Array.from(event.target.files))
        event.target.value = ''
    }

    onDragOver(event) {
        event.preventDefault()
        this.dropzoneTarget.classList.add('dropzone--active')
    }

    onDragLeave(event) {
        if (!this.dropzoneTarget.contains(event.relatedTarget)) {
            this.dropzoneTarget.classList.remove('dropzone--active')
        }
    }

    onDrop(event) {
        event.preventDefault()
        this.dropzoneTarget.classList.remove('dropzone--active')
        this.#addFiles(Array.from(event.dataTransfer.files))
    }

    remove(event) {
        const id = parseInt(event.currentTarget.dataset.fileId)
        this.#files.delete(id)
        this.#order = this.#order.filter(i => i !== id)
        this.#render()
    }

    submit(event) {
        event.preventDefault()
        if (this.#order.length === 0) return

        const formData = new FormData()
        this.#order.forEach(id => formData.append('pdfs[]', this.#files.get(id)))

        this.submitTarget.disabled = true
        this.submitTarget.textContent = 'Processing…'

        fetch(this.element.action, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => console.log('Submitted:', data))
            .catch(err => console.error(err))
            .finally(() => {
                this.submitTarget.disabled = false
                this.submitTarget.textContent = 'Convert to HTML'
            })
    }

    #addFiles(files) {
        files
            .filter(f => f.type === 'application/pdf')
            .forEach(file => {
                const id = ++this.#counter
                this.#files.set(id, file)
                this.#order.push(id)
            })
        this.#render()
    }

    #syncOrderFromDOM() {
        const items = this.listTarget.querySelectorAll('[data-file-id]')
        this.#order = Array.from(items).map(el => parseInt(el.dataset.fileId))
    }

    #initSortable() {
        this.#sortable?.destroy()
        this.#sortable = Sortable.create(this.listTarget, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'file-item--ghost',
            onEnd: () => this.#syncOrderFromDOM(),
        })
    }

    #render() {
        const hasFiles = this.#order.length > 0
        this.emptyTarget.hidden = hasFiles
        this.listTarget.hidden = !hasFiles
        this.submitTarget.disabled = !hasFiles

        this.listTarget.innerHTML = this.#order.map((id, index) => {
            const file = this.#files.get(id)
            return `
                <div class="file-item" data-file-id="${id}">
                    <span class="drag-handle" title="Drag to reorder">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="9"  cy="4"  r="1.5"/>
                            <circle cx="15" cy="4"  r="1.5"/>
                            <circle cx="9"  cy="12" r="1.5"/>
                            <circle cx="15" cy="12" r="1.5"/>
                            <circle cx="9"  cy="20" r="1.5"/>
                            <circle cx="15" cy="20" r="1.5"/>
                        </svg>
                    </span>
                    <span class="file-order">${index + 1}</span>
                    <div class="file-info">
                        <span class="file-name">${this.#escapeHtml(file.name)}</span>
                        <span class="file-size">${this.#formatSize(file.size)}</span>
                    </div>
                    <button type="button" class="file-remove"
                        data-action="click->pdf-upload#remove"
                        data-file-id="${id}"
                        title="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>`
        }).join('')

        this.#initSortable()
    }

    #escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
    }

    #formatSize(bytes) {
        if (bytes < 1024) return `${bytes} B`
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    }
}
