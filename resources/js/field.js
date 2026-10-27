import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
  app.component('index-chunked-file', IndexField)
  app.component('detail-chunked-file', DetailField)
  app.component('form-chunked-file', FormField)
})
