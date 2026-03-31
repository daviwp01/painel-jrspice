<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Search, ChevronDown, Check, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Selecione...'
    },
    label: String,
    icon: [Object, Function], // Lucide icon component
    disabled: {
        type: Boolean,
        default: false
    },
    direction: {
        type: String,
        default: 'down' // 'down' or 'up'
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const selectOption = (option) => {
    emit('update:modelValue', option.id);
    emit('change', option.id);
    closeDropdown();
};

const selectedOption = computed(() => {
    return props.options.find(opt => opt.id == props.modelValue);
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(opt => 
        opt.name.toLowerCase().includes(query)
    );
});

// Click outside to close
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Label -->
        <label v-if="label" class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 flex items-center gap-1.5 transition-colors" :class="{ 'text-blue-600': isOpen }">
            <component :is="icon" class="w-3.5 h-3.5" v-if="icon" />
            {{ label }}
        </label>

        <!-- Trigger -->
        <div 
            @click="toggleDropdown"
            :class="[
                'w-full bg-slate-50 border transition-all duration-200 cursor-pointer rounded-xl px-3 py-3 flex items-center justify-between group',
                isOpen ? 'bg-white border-blue-500 ring-4 ring-blue-50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-100/50',
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        >
            <div class="flex items-center gap-2 overflow-hidden">
                <span v-if="!selectedOption" class="text-slate-400 text-sm font-medium uppercase truncate">{{ placeholder }}</span>
                <span v-else class="text-slate-800 text-sm font-bold uppercase truncate">{{ selectedOption.name }}</span>
            </div>
            <ChevronDown class="w-4 h-4 text-slate-400 transition-transform duration-300" :class="{ 'rotate-180 text-blue-500': isOpen }" />
        </div>

        <!-- Dropdown Menu -->
        <div 
            v-if="isOpen" 
            :class="[
                'absolute z-[100] left-0 right-0 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden duration-200',
                direction === 'up' ? 'bottom-full mb-3 animate-in fade-in slide-in-from-bottom-2' : 'top-full mt-2 animate-in fade-in slide-in-from-top-2'
            ]"
        >
            <!-- Search Input -->
            <div class="p-2 border-b border-slate-100 bg-slate-50/50">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar..."
                        class="w-full bg-white border-slate-200 rounded-lg text-[11px] font-bold py-2 pl-9 pr-8 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all uppercase"
                        @click.stop
                        autofocus
                    >
                    <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 hover:bg-slate-100 rounded-md transition-colors">
                        <X class="w-3 h-3 text-slate-400" />
                    </button>
                </div>
            </div>

            <!-- Options List -->
            <div class="max-h-[280px] overflow-y-auto p-1 scrollbar-thin scrollbar-thumb-slate-200">
                <div 
                    v-for="option in filteredOptions" 
                    :key="option.id"
                    @click="selectOption(option)"
                    :class="[
                        'flex items-center justify-between px-3 py-2.5 rounded-lg cursor-pointer transition-all group mb-0.5',
                        modelValue == option.id ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
                    ]"
                >
                    <span class="text-xs font-bold uppercase tracking-wide truncate">{{ option.name }}</span>
                    <Check v-if="modelValue == option.id" class="w-3.5 h-3.5 text-white stroke-[3]" />
                </div>

                <!-- Empty State -->
                <div v-if="filteredOptions.length === 0" class="py-10 px-4 text-center">
                    <div class="w-10 h-10 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-3">
                        <Search class="w-5 h-5" />
                    </div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-relaxed">Nenhum resultado para "{{ searchQuery }}"</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 20px;
}
</style>
