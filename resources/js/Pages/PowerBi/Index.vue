<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import * as pbi from 'powerbi-client';

const props = defineProps({
    embedConfig: Object,
});

const reportContainer = ref(null);

onMounted(() => {
    if (props.embedConfig && reportContainer.value) {
        console.log('Power BI Embed Config:', props.embedConfig);

        try {
            const powerbi = new pbi.service.Service(pbi.factories.hpmFactory, pbi.factories.wpmpFactory, pbi.factories.routerFactory);

            const config = {
                type: 'report',
                tokenType: 1, // Models.TokenType.Embed
                accessToken: props.embedConfig.accessToken,
                embedUrl: props.embedConfig.embedUrl,
                id: props.embedConfig.id,
                hostname: "https://app.powerbi.com", // Force the correct hostname
                permissions: pbi.models.Permissions.All,
                settings: {
                    filterPaneEnabled: false,
                    navContentPaneEnabled: false,
                }
            };

            console.log('Initializing Power BI embed with config:', config);

            const report = powerbi.embed(reportContainer.value, config);

            report.on('loaded', () => {
                console.log('Report loaded successfully');
            });

            report.on('error', (event) => {
                console.error('Power BI Report Error:', event.detail);
            });

        } catch (error) {
            console.error('Error initializing Power BI embed:', error);
            alert('Failed to initialize Power BI report: ' + error.message);
        }
    } else {
        console.warn('Embed config or container missing', props.embedConfig, reportContainer.value);
    }
});
</script>

<template>
    <Head title="Power BI Report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Power BI Report
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="embedConfig" ref="reportContainer" class="aspect-w-16 aspect-h-9" style="height: 600px;"></div>
                        <div v-else class="text-center py-10 text-gray-500">
                            Unable to load Power BI configuration. Please check your credentials.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
