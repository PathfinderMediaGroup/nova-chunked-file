<template>
  <DefaultField
    :field="currentField"
    :label-for="labelFor"
    :errors="errors"
    :show-help-text="!isReadonly && showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <div class="space-y-4">
        <!-- Existing file -->
        <div
          v-if="hasValue && previewFile && files.length === 0"
          class="grid grid-cols-4 gap-x-6 gap-y-2"
        >
          <FilePreviewBlock
            :file="previewFile"
            :removable="shouldShowRemoveButton"
            @removed="confirmRemoval"
            :rounded="field.rounded"
            :dusk="`${field.attribute}-delete-link`"
          />
        </div>

        <ConfirmUploadRemovalModal
          :show="removeModalOpen"
          @confirm="removeUploadedFile"
          @close="closeRemoveModal"
        />

        <DropZone
          v-if="shouldShowField"
          :files="files"
          @file-changed="handleFileChange"
          @file-removed="cancelUpload"
          :rounded="field.rounded"
          :accepted-types="field.acceptedTypes"
          :disabled="file?.processing"
          :input-dusk="field.attribute"
        />

        <p v-if="uploadError" class="text-xs text-red-500">{{ uploadError }}</p>
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova'

const ENDPOINT = '/nova-vendor/chunked-file/chunk'
const MAX_ATTEMPTS = 4

function createFile(file) {
  return {
    name: file.name,
    extension: file.name.split('.').pop(),
    type: file.type,
    originalFile: file,
    vapor: false,
    processing: false,
    progress: 0,
  }
}

function uuid() {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID()
  }

  return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c =>
    (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)
  )
}

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))

export default {
  emits: ['file-upload-started', 'file-upload-finished', 'file-deleted'],

  mixins: [HandlesValidationErrors, DependentFormField],

  inject: ['removeFile'],

  expose: ['beforeRemove'],

  data: () => ({
    previewFile: null,
    file: null,
    token: null,
    uploadError: null,
    abortController: null,
    removeModalOpen: false,
    deleted: false,
  }),

  mounted() {
    if (this.hasValue) {
      this.previewFile = createFile({
        name: this.currentField.value,
        type: this.currentField.value.split('.').pop(),
      })
    }

    this.field.fill = formData => {
      if (this.file && this.token) {
        formData.append(this.fieldAttribute, this.token)
      }
    }
  },

  beforeUnmount() {
    this.abortController?.abort()
  },

  methods: {
    handleFileChange(newFiles) {
      this.cancelUpload()
      this.file = createFile(newFiles[0])
      this.upload()
    },

    cancelUpload() {
      if (this.abortController) {
        this.abortController.abort()
        this.abortController = null
        this.$emit('file-upload-finished')
      }

      this.file = null
      this.token = null
      this.uploadError = null
    },

    async upload() {
      const file = this.file
      const blob = file.originalFile
      const chunkSize = this.currentField.chunkSize
      const totalChunks = Math.max(1, Math.ceil(blob.size / chunkSize))
      const uploadId = uuid()
      const controller = new AbortController()

      this.abortController = controller
      this.uploadError = null
      file.processing = true
      this.$emit('file-upload-started')

      try {
        let index = 0

        while (index < totalChunks) {
          const offset = index * chunkSize
          const data = await this.sendChunk(
            {
              upload_id: uploadId,
              chunk_index: index,
              total_chunks: totalChunks,
              filename: blob.name,
              chunk: blob.slice(offset, offset + chunkSize),
            },
            controller.signal,
            loaded => {
              file.progress = Math.min(
                99,
                Math.round(((offset + loaded) / Math.max(blob.size, 1)) * 100)
              )
            }
          )

          if (data.complete) {
            this.token = data.token
            break
          }

          index = data.next_chunk
        }

        file.progress = 100
      } catch (error) {
        if (controller.signal.aborted) {
          return
        }

        this.file = null
        this.token = null
        this.uploadError =
          error.response?.data?.message ?? this.__('The file could not be uploaded.')
      } finally {
        if (this.abortController === controller) {
          file.processing = false
          this.abortController = null
          this.$emit('file-upload-finished')
        }
      }
    },

    async sendChunk(payload, signal, onProgress) {
      for (let attempt = 1; ; attempt++) {
        const formData = new FormData()
        Object.entries(payload).forEach(([key, value]) =>
          formData.append(key, value)
        )

        try {
          const { data } = await Nova.request().post(ENDPOINT, formData, {
            signal,
            onUploadProgress: event => onProgress(event.loaded),
          })

          return data
        } catch (error) {
          const status = error.response?.status

          // Server is at a different chunk than we are: resume from there.
          if (status === 409) {
            return error.response.data
          }

          const retryable = !status || status >= 500 || status === 429

          if (signal.aborted || !retryable || attempt >= MAX_ATTEMPTS) {
            throw error
          }

          await sleep(1000 * 2 ** (attempt - 1))
        }
      }
    },

    confirmRemoval() {
      this.removeModalOpen = true
    },

    closeRemoveModal() {
      this.removeModalOpen = false
    },

    beforeRemove() {
      this.removeUploadedFile()
    },

    async removeUploadedFile() {
      try {
        await this.removeFile(this.fieldAttribute)
        this.$emit('file-deleted')
        this.deleted = true
        this.file = null
        Nova.success(this.__('The file was deleted!'))
      } finally {
        this.closeRemoveModal()
      }
    },
  },

  computed: {
    files() {
      return this.file ? [this.file] : []
    },

    labelFor() {
      let name = this.resourceName

      if (this.relatedResourceName) {
        name += '-' + this.relatedResourceName
      }

      return `file-${name}-${this.fieldAttribute}`
    },

    hasValue() {
      return Boolean(this.currentField.value) && !this.deleted
    },

    shouldShowField() {
      return !this.currentlyIsReadonly
    },

    shouldShowRemoveButton() {
      return Boolean(this.currentField.deletable && !this.currentlyIsReadonly)
    },
  },
}
</script>
