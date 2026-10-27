<script>
import { h, resolveComponent } from 'vue'

// Display is identical to Nova's own File field, so just delegate to it.
export default {
  inheritAttrs: false,

  render() {
    return h(resolveComponent('detail-file-field'), this.$attrs)
  },
}
</script>
