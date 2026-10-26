import Alpine from 'alpinejs'
import commentSheet from './bleep/comments/commentSheet.js'
import notificationsBell from './notifications/bell.js'

window.Alpine = Alpine

// comment sheet modal
Alpine.data('commentSheet', commentSheet)

// notification bell dropdown + realtime/polling
Alpine.data('notificationsBell', notificationsBell)

Alpine.start()
