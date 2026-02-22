import Alpine from 'alpinejs'
import commentSheet from './bleep/comments/commentSheet.js'

window.Alpine = Alpine

// comment sheet modal
Alpine.data('commentSheet', commentSheet)

Alpine.start()
