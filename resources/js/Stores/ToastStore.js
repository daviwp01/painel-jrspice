import { reactive } from 'vue';

export const toast = reactive({
    items: [],
    add(message, type = 'success') {
        const id = Date.now();
        this.items.unshift({ id, message, type });

        setTimeout(() => {
            this.remove(id);
        }, 5000);
    },
    remove(id) {
        const index = this.items.findIndex(item => item.id === id);
        if (index > -1) {
            this.items.splice(index, 1);
        }
    }
});
