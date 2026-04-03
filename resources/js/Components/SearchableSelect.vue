<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Search, ChevronDown, Check, X, Lock } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';

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
    },
    withFlag: {
        type: Boolean,
        default: false
    },
    flagProperty: {
        type: String,
        default: 'name'
    },
    searchable: {
        type: Boolean,
        default: true
    },
    variant: {
        type: String,
        default: 'light' // 'light' or 'dark'
    }
});

const emit = defineEmits(['update:modelValue', 'change', 'locked-click']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);
const searchInput = ref(null);
const calculatedDirection = ref(props.direction);

const updateDirection = () => {
    if (!dropdownRef.value) return;
    const rect = dropdownRef.value.getBoundingClientRect();
    const spaceAbove = rect.top;
    
    // Se a direção for 'up' mas tivermos menos de 220px de espaço no teto, forçamos 'down'
    if (props.direction === 'up' && spaceAbove < 220) {
        calculatedDirection.value = 'down';
    } else {
        calculatedDirection.value = props.direction;
    }
};

watch(() => props.direction, (newDir) => {
    calculatedDirection.value = newDir;
});

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        updateDirection();
        searchQuery.value = '';
        if (props.searchable) {
            nextTick(() => {
                searchInput.value?.focus();
            });
        }
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const selectOption = (option) => {
    if (option.is_locked) {
        emit('locked-click', option);
        return;
    }
    emit('update:modelValue', option.id);
    emit('change', option.id);
    closeDropdown();
};

const selectedOption = computed(() => {
    return props.options.find(opt => opt.id == props.modelValue);
});

const normalize = (str) => 
    str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = normalize(searchQuery.value);
    return props.options.filter(opt => 
        normalize(opt.name || '').includes(query)
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
// Resolve flag name from property path (e.g., 'country.name')
const resolveFlagName = (option) => {
    if (!option) return '';
    const path = props.flagProperty.split('.');
    let val = option;
    for (const key of path) {
        if (val && val[key] !== undefined) {
            val = val[key];
        } else {
            return '';
        }
    }
    return val;
};
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Label -->
        <label 
            v-if="label" 
            @click="toggleDropdown"
            class="text-[10px] font-black uppercase tracking-widest mb-1.5 flex items-center gap-1.5 transition-colors cursor-pointer" 
            :class="[
                isOpen ? 'text-blue-500' : (variant === 'dark' ? 'text-slate-500' : 'text-slate-500')
            ]"
        >
            <component :is="icon" class="w-3.5 h-3.5" v-if="icon" />
            {{ label }}
        </label>

        <!-- Trigger -->
        <div 
            @click="toggleDropdown"
            :class="[
                'w-full border transition-all duration-200 cursor-pointer rounded-xl px-3 py-3 flex items-center justify-between group',
                variant === 'dark' 
                ? (isOpen ? 'bg-[#1e293b] border-blue-500/50 shadow-[0_0_15px_rgba(59,130,246,0.1)]' : 'bg-[#1e293b]/40 border-slate-800 hover:border-slate-700 hover:bg-[#1e293b]/60')
                : (isOpen ? 'bg-white border-blue-500 ring-4 ring-blue-50' : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-white/50'),
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        >
            <div class="flex items-center gap-2 overflow-hidden">
                <span v-if="!selectedOption" class="text-slate-500 text-sm font-medium uppercase truncate">{{ placeholder }}</span>
                <div v-else class="flex items-center gap-2 overflow-hidden">
                    <CountryFlag v-if="withFlag" :name="selectedOption.name" :code="resolveFlagName(selectedOption)" class-name="w-4 h-3 object-cover rounded-sm border border-slate-800/10" />
                    <span :class="[variant === 'dark' ? 'text-slate-200' : 'text-slate-800']" class="text-sm font-bold uppercase truncate">{{ selectedOption.name }}</span>
                </div>
            </div>
            <ChevronDown class="w-4 h-4 text-slate-500 transition-transform duration-300" :class="{ 'rotate-180 text-blue-500': isOpen }" />
        </div>

        <div 
            v-if="isOpen" 
            :class="[
                'absolute z-[100] left-0 right-0 border rounded-2xl shadow-2xl overflow-hidden duration-200',
                variant === 'dark' ? 'bg-[#0f172a] border-slate-800' : 'bg-white border-slate-200',
                calculatedDirection === 'up' ? 'bottom-full mb-3 animate-in fade-in slide-in-from-bottom-2' : 'top-full mt-2 animate-in fade-in slide-in-from-top-2'
            ]"
        >
            <!-- Search Input -->
            <div v-if="searchable" :class="[variant === 'dark' ? 'p-2 border-b border-slate-800 bg-[#1e293b]/30' : 'p-2 border-b border-slate-100 bg-white']">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
                    <input 
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar..."
                        :class="[
                            'w-full rounded-lg text-[11px] font-bold py-2 pl-9 pr-8 transition-all uppercase',
                            variant === 'dark' ? 'bg-[#0f172a] border-slate-800 text-slate-200 focus:ring-blue-500 focus:border-blue-500' : 'bg-white border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-800'
                        ]"
                        @click.stop
                    >
                    <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 hover:bg-slate-800 rounded-md transition-colors">
                        <X class="w-3 h-3 text-slate-500" />
                    </button>
                </div>
            </div>

            <!-- Options List -->
            <div :class="['max-h-[280px] overflow-y-auto p-1 scrollbar-thin', variant === 'dark' ? 'scrollbar-thumb-slate-800' : 'scrollbar-thumb-slate-200']">
                <div 
                    v-for="option in filteredOptions" 
                    :key="option.id"
                    @click="selectOption(option)"
                    :class="[
                        'flex items-center justify-between px-3 py-2.5 rounded-lg cursor-pointer transition-all group mb-0.5',
                        modelValue == option.id 
                            ? 'bg-blue-600 text-white shadow-md' 
                            : (variant === 'dark' ? 'text-slate-400 hover:bg-white/5 hover:text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'),
                        option.is_locked ? 'opacity-40 grayscale cursor-default border border-dashed border-slate-800 bg-black/10' : ''
                    ]"
                >
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <CountryFlag v-if="withFlag" :name="option.name" :code="resolveFlagName(option)" class-name="w-4 h-3 object-cover rounded-sm border border-slate-800/20" />
                        <span class="text-xs font-bold uppercase tracking-wide truncate">{{ option.name }}</span>
                    </div>
                    <Lock v-if="option.is_locked" class="w-3 h-3 text-slate-500 group-hover:text-blue-400 transition-colors" />
                    <Check v-else-if="modelValue == option.id" class="w-3.5 h-3.5 text-white stroke-[3]" />
                </div>

                <!-- Empty State -->
                <div v-if="filteredOptions.length === 0" class="py-10 px-4 text-center">
                    <div class="w-10 h-10 bg-slate-900/50 text-slate-500 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-800">
                        <Search class="w-5 h-5" />
                    </div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest leading-relaxed">Nenhum resultado para "{{ searchQuery }}"</p>
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
