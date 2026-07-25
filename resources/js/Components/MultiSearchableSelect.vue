<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Search, ChevronDown, Check, X, Users } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [Array, String, Number],
        default: () => []
    },
    options: {
        type: [Array, Object],
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Selecione os usuários...'
    },
    label: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    },
    maxInitial: {
        type: Number,
        default: 20
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);
const searchInput = ref(null);

const selectedIdsList = computed(() => {
    if (!props.modelValue) return [];
    if (Array.isArray(props.modelValue)) return props.modelValue;
    return [props.modelValue];
});

const isSelected = (id) => {
    return selectedIdsList.value.some(val => String(val) === String(id));
};

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            searchInput.value?.focus();
        });
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const toggleOption = (id) => {
    const current = [...selectedIdsList.value];
    const index = current.findIndex(val => String(val) === String(id));
    if (index > -1) {
        current.splice(index, 1);
    } else {
        current.push(id);
    }
    emit('update:modelValue', current);
    emit('change', current);
};

const removeOption = (id, event) => {
    event?.stopPropagation();
    const current = selectedIdsList.value.filter(val => String(val) !== String(id));
    emit('update:modelValue', current);
    emit('change', current);
};

const clearAll = (event) => {
    event?.stopPropagation();
    emit('update:modelValue', []);
    emit('change', []);
};

const normalize = (str) => 
    (str || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

const optionsList = computed(() => {
    if (!props.options) return [];
    if (Array.isArray(props.options)) return props.options;
    if (typeof props.options === 'object') return Object.values(props.options);
    return [];
});

const filteredOptions = computed(() => {
    const list = optionsList.value;
    if (!searchQuery.value) {
        return list.slice(0, props.maxInitial);
    }
    const q = normalize(searchQuery.value);
    return list.filter(opt => {
        const nameMatch = normalize(opt.name).includes(q);
        const emailMatch = normalize(opt.email).includes(q);
        const phoneMatch = normalize(opt.phone).includes(q);
        const companyMatch = normalize(opt.company_name).includes(q);
        const countryMatch = normalize(opt.country).includes(q);
        const typeMatch = normalize(opt.type).includes(q);
        return nameMatch || emailMatch || phoneMatch || companyMatch || countryMatch || typeMatch;
    }).slice(0, 50);
});

const selectedObjects = computed(() => {
    return selectedIdsList.value
        .filter(id => !String(id).startsWith('client_'))
        .map(id => {
            const found = optionsList.value.find(opt => String(opt.id) === String(id));
            if (found) return found;
            return {
                id: id,
                name: `Usuário #${id}`
            };
        });
});

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Label -->
        <div v-if="label" class="flex items-center justify-between mb-1.5">
            <label 
                @click="toggleDropdown"
                class="text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-1.5 cursor-pointer"
            >
                <Users class="w-3.5 h-3.5 text-[#b2862e]" />
                {{ label }}
            </label>
            <button 
                v-if="selectedIdsList.length > 0" 
                @click="clearAll"
                type="button"
                class="text-[9px] font-bold text-slate-400 hover:text-red-500 uppercase tracking-tighter transition-colors"
            >
                Limpar ({{ selectedIdsList.length }})
            </button>
        </div>

        <!-- Trigger Box -->
        <div 
            @click="toggleDropdown"
            class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 flex items-center justify-between cursor-pointer transition-all duration-150 select-none min-h-[42px]"
            :class="[
                isOpen ? 'border-blue-500 ring-2 ring-blue-500/20 bg-white' : 'border-slate-200 hover:border-slate-300',
                disabled ? 'opacity-50 cursor-not-allowed bg-slate-100' : ''
            ]"
        >
            <div class="flex flex-wrap items-center gap-1.5 flex-1 min-w-0 pr-2">
                <span v-if="selectedIdsList.length === 0" class="text-xs font-medium text-slate-400 truncate">
                    {{ placeholder }}
                </span>
                <template v-else>
                    <span 
                        v-for="item in selectedObjects" 
                        :key="item.id"
                        class="inline-flex items-center gap-1 bg-blue-50 border border-blue-200 text-blue-700 px-2 py-0.5 rounded-lg text-xs font-bold"
                    >
                        <span class="truncate max-w-[120px]">{{ item.name }}</span>
                        <X 
                            @click="removeOption(item.id, $event)" 
                            class="w-3 h-3 text-blue-500 hover:text-red-500 transition-colors" 
                        />
                    </span>
                </template>
            </div>

            <ChevronDown 
                class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" 
                :class="{ 'rotate-180 text-blue-500': isOpen }"
            />
        </div>

        <!-- Dropdown Menu -->
        <div 
            v-if="isOpen"
            class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden animate-in fade-in slide-in-from-top-1 duration-150"
        >
            <!-- Search Box inside dropdown -->
            <div class="p-2.5 border-b border-slate-100 bg-slate-50/50 relative">
                <Search class="w-3.5 h-3.5 absolute left-5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input 
                    ref="searchInput"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar por nome, e-mail, telefone..."
                    class="w-full text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 placeholder:text-slate-400"
                />
            </div>

            <!-- List Options -->
            <div class="max-h-56 overflow-y-auto divide-y divide-slate-50">
                <div 
                    v-for="option in filteredOptions" 
                    :key="option.id"
                    @click="toggleOption(option.id)"
                    class="px-3.5 py-2.5 text-xs flex items-center justify-between hover:bg-blue-50/60 cursor-pointer transition-colors"
                    :class="{ 'bg-blue-50/30': isSelected(option.id) }"
                >
                    <div class="min-w-0 flex-1 pr-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-800 truncate">{{ option.name }}</span>
                            <span v-if="option.country || option.type" class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.2 bg-slate-100 text-slate-500 rounded">
                                {{ option.country || option.type }}
                            </span>
                        </div>
                        <div v-if="option.email || option.phone" class="text-[10px] text-slate-400 truncate mt-0.5">
                            <span v-if="option.email">{{ option.email }}</span>
                            <span v-if="option.email && option.phone"> • </span>
                            <span v-if="option.phone">{{ option.phone }}</span>
                        </div>
                    </div>

                    <div 
                        class="w-4 h-4 rounded border flex items-center justify-center transition-colors shrink-0"
                        :class="isSelected(option.id) ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-300 bg-white'"
                    >
                        <Check v-if="isSelected(option.id)" class="w-3 h-3 stroke-[3]" />
                    </div>
                </div>

                <div v-if="!filteredOptions.length" class="px-4 py-6 text-center text-xs text-slate-400 font-medium">
                    Nenhum usuário encontrado
                </div>
            </div>

            <!-- Footer indicator -->
            <div class="px-3 py-2 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                <span>Exibindo {{ filteredOptions.length }} de {{ optionsList.length }}</span>
                <span>{{ selectedIdsList.length }} selecionados</span>
            </div>
        </div>
    </div>
</template>
