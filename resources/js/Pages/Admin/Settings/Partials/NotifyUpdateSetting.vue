<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Bell, Send, Users, Check, Search, Loader2, ShieldAlert } from 'lucide-vue-next';

const props = defineProps({
    users: {
        type: Array,
        default: () => []
    }
});

const searchQuery = ref('');
const selectedUsers = ref([]);

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    return props.users.filter(user =>
        user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const isAllSelected = computed(() => {
    return filteredUsers.value.length > 0 &&
           filteredUsers.value.every(user => selectedUsers.value.includes(user.id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedUsers.value = selectedUsers.value.filter(id =>
            !filteredUsers.value.find(u => u.id === id)
        );
    } else {
        const newIds = filteredUsers.value
            .map(u => u.id)
            .filter(id => !selectedUsers.value.includes(id));
        selectedUsers.value = [...selectedUsers.value, ...newIds];
    }
};

const toggleUser = (userId) => {
    if (selectedUsers.value.includes(userId)) {
        selectedUsers.value = selectedUsers.value.filter(id => id !== userId);
    } else {
        selectedUsers.value.push(userId);
    }
};

const selectNonMasters = () => {
    const nonMasterIds = filteredUsers.value
        .filter(u => !u.is_master)
        .map(u => u.id);

    // If they are all already selected, clear them
    const allNonMastersSelected = nonMasterIds.length > 0 &&
        nonMasterIds.every(id => selectedUsers.value.includes(id));

    if (allNonMastersSelected) {
        selectedUsers.value = selectedUsers.value.filter(id => !nonMasterIds.includes(id));
    } else {
        const newIds = nonMasterIds.filter(id => !selectedUsers.value.includes(id));
        selectedUsers.value = [...selectedUsers.value, ...newIds];
    }
};

const form = useForm({
    user_ids: []
});

const sendNotification = () => {
    form.user_ids = selectedUsers.value;
    form.post(route('admin.settings.notify'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedUsers.value = [];
        }
    });
};
</script>

<template>
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <!-- Header -->
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-amber-100 rounded-xl text-amber-600 shadow-sm">
                    <Bell class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Notify Users of Update') }}</h3>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-0.5">{{ $t('Send an email alert to selected users') }}</p>
                </div>
            </div>

            <div v-if="selectedUsers.length > 0" class="animate-in fade-in slide-in-from-right-4 duration-300">
                <button
                    @click="sendNotification"
                    :disabled="form.processing"
                    class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white text-xs font-black uppercase tracking-[0.2em] rounded-xl hover:bg-indigo-700 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-indigo-100 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                    <Send v-else class="w-4 h-4 mr-2" />
                    {{ $t('Send Notification') }} ({{ selectedUsers.length }})
                </button>
            </div>
        </div>

        <div class="p-8 space-y-6">
            <!-- Search and Select All -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <div class="relative flex-1 max-w-xl">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="$t('Search by name or email...')"
                        class="w-full pl-10 pr-4 py-2.5 bg-white border-slate-200 rounded-lg text-sm focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 transition-all"
                    >
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        @click="selectNonMasters"
                        class="flex items-center space-x-2 px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all border-2 border-slate-200 text-slate-500 hover:bg-slate-100"
                    >
                        <span>{{ $t('Only Common Users') }}</span>
                    </button>

                    <button
                        @click="toggleSelectAll"
                        class="flex items-center space-x-2 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all"
                        :class="isAllSelected ? 'text-indigo-600 bg-indigo-50' : 'text-slate-500 hover:bg-slate-200'"
                    >
                        <div class="w-5 h-5 rounded border-2 flex items-center justify-center" :class="isAllSelected ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300 bg-white'">
                            <Check v-if="isAllSelected" class="w-3.5 h-3.5 text-white" />
                        </div>
                        <span>{{ isAllSelected ? $t('Deselect All') : $t('Select All Listed') }}</span>
                    </button>
                </div>
            </div>

            <!-- Users Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                <div
                    v-for="user in filteredUsers"
                    :key="user.id"
                    @click="toggleUser(user.id)"
                    class="flex items-center p-4 rounded-xl border-2 transition-all cursor-pointer group"
                    :class="selectedUsers.includes(user.id) ? 'border-indigo-500 bg-indigo-50/30' : 'border-slate-100 bg-white hover:border-indigo-200'"
                >
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center font-black text-xs transition-all mr-3"
                         :class="selectedUsers.includes(user.id) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-400'">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-1.5">
                            <p class="text-sm font-black text-slate-800 truncate">{{ user.name }}</p>
                            <ShieldAlert v-if="user.is_master" class="w-3 h-3 text-amber-500" :title="$t('Master Admin')" />
                        </div>
                        <p class="text-[10px] text-slate-400 font-bold truncate uppercase tracking-tighter">{{ user.email }}</p>
                    </div>
                    <div class="ml-2">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                             :class="selectedUsers.includes(user.id) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-200'">
                            <Check v-if="selectedUsers.includes(user.id)" class="w-3 h-3 text-white" />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredUsers.length === 0" class="col-span-full py-12 text-center text-slate-400">
                    <Users class="w-12 h-12 mx-auto mb-3 opacity-20" />
                    <p class="font-bold uppercase tracking-widest text-[10px]">{{ $t('No users matched your search') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

.animate-in {
    animation-duration: 0.3s;
    animation-fill-mode: both;
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes slide-in-from-right-4 { from { transform: translateX(1rem); } to { transform: translateX(0); } }
.fade-in { animation-name: fade-in; }
.slide-in-from-right-4 { animation-name: slide-in-from-right-4; }
</style>
