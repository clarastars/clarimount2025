<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex items-center justify-between my-2">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('print_station.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ t('print_station.description') }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <!-- JSPrintManager Printer Selection -->
                    <div class="flex items-center gap-2">
                        <Icon name="Printer" class="h-4 w-4 text-gray-500" />
                        <div class="flex flex-col">
                            <select
                                v-model="selectedPrinter"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-md px-2 py-1 bg-white dark:bg-gray-800"
                                :disabled="loadingPrinters"
                            >
                                <option value="">
                                    {{ loadingPrinters ? 'Loading printers...' : 
                                       availablePrinters.length === 0 ? 'No printers found' : 
                                       'Select Printer' }}
                                </option>
                                <option v-for="printer in availablePrinters" :key="printer" :value="printer">
                                    {{ printer }}
                                </option>
                            </select>
                            <span v-if="loadingPrinters || printerStatus !== 'Initializing...'" class="text-xs text-gray-500 mt-1">
                                {{ printerStatus }}
                            </span>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="loadPrintersFromJSPM"
                            :disabled="loadingPrinters"
                        >
                            <Icon name="RefreshCw" class="h-3 w-3" />
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="debugJSPrintManager"
                        >
                            Debug
                        </Button>
                    </div>
                    
                    <Button @click="refreshJobs" :disabled="loading">
                        <Icon name="RefreshCw" class="mr-2 h-4 w-4" />
                        {{ t('print_station.refresh') }}
                    </Button>
                </div>
            </div>
            <!-- Content Section -->
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <!-- Statistics -->
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-yellow-100 text-yellow-600">
                                        <Icon name="Clock" class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ t('print_station.pending_jobs') }}
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ pendingJobs.length }}
                                    </dd>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 text-blue-600">
                                        <Icon name="Printer" class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ t('print_station.processing_jobs') }}
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ processingJobs.length }}
                                    </dd>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600">
                                        <Icon name="CheckCircle" class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ t('print_station.completed_today') }}
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ statistics.completed_today || 0 }}
                                    </dd>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-600">
                                        <Icon name="XCircle" class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ t('print_station.failed_today') }}
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ statistics.failed_today || 0 }}
                                    </dd>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Print Queue -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Pending Jobs -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Icon name="Clock" class="h-5 w-5 text-yellow-600" />
                                {{ t('print_station.pending_queue') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="pendingJobs.length === 0" class="text-center py-8">
                                <Icon name="Inbox" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                <p class="text-gray-500">{{ t('print_station.no_pending_jobs') }}</p>
                            </div>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="job in pendingJobs"
                                    :key="job.id"
                                    class="border rounded-lg p-4 bg-yellow-50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <Badge :variant="getPriorityVariant(job.priority)">
                                                    {{ job.priority }}
                                                </Badge>
                                                <span class="text-sm font-medium">{{ job.job_id }}</span>
                                            </div>
                                            <div class="mt-2">
                                                <p class="font-semibold">{{ job.asset.asset_tag }}</p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ job.asset.model_name }} - {{ job.company.name }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-500">
                                                    {{ t('print_station.requested_by') }} {{ job.user.name }}
                                                </p>
                                                <p v-if="job.comment" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                    💬 {{ job.comment }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Button size="sm" @click="processPrintJob(job)">
                                                <Icon name="Play" class="h-4 w-4" />
                                            </Button>
                                            <Button size="sm" variant="outline" @click="cancelPrintJob(job)">
                                                <Icon name="X" class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Processing Jobs -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Icon name="Printer" class="h-5 w-5 text-blue-600" />
                                {{ t('print_station.processing_queue') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="processingJobs.length === 0" class="text-center py-8">
                                <Icon name="Printer" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                <p class="text-gray-500">{{ t('print_station.no_processing_jobs') }}</p>
                            </div>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="job in processingJobs"
                                    :key="job.id"
                                    class="border rounded-lg p-4 bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <Badge variant="secondary">
                                                    {{ job.priority }}
                                                </Badge>
                                                <span class="text-sm font-medium">{{ job.job_id }}</span>
                                            </div>
                                            <div class="mt-2">
                                                <p class="font-semibold">{{ job.asset.asset_tag }}</p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ job.asset.model_name }} - {{ job.company.name }}
                                                </p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <div class="animate-spin h-3 w-3 border-2 border-blue-600 border-t-transparent rounded-full"></div>
                                                    <span class="text-xs text-blue-600">{{ t('print_station.printing') }}</span>
                                                </div>
                                                <p v-if="job.comment" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                    💬 {{ job.comment }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Button size="sm" variant="outline" @click="completePrintJob(job)">
                                                <Icon name="Check" class="h-4 w-4" />
                                            </Button>
                                            <Button size="sm" variant="destructive" @click="failPrintJob(job)">
                                                <Icon name="X" class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Recent Activity -->
                <div class="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('print_station.recent_activity') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentJobs.length === 0" class="text-center py-4">
                                <p class="text-gray-500">{{ t('print_station.no_recent_activity') }}</p>
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="job in recentJobs"
                                    :key="job.id"
                                    class="flex items-center justify-between p-2 rounded border"
                                >
                                    <div class="flex items-center gap-3">
                                        <Icon 
                                            :name="job.status === 'completed' ? 'CheckCircle' : 'XCircle'" 
                                            :class="job.status === 'completed' ? 'text-green-600' : 'text-red-600'"
                                            class="h-4 w-4"
                                        />
                                        <span class="font-medium">{{ job.asset.asset_tag }}</span>
                                        <Badge :variant="job.status === 'completed' ? 'default' : 'destructive'" size="sm">
                                            {{ job.status }}
                                        </Badge>
                                    </div>
                                    <span class="text-sm text-gray-500">
                                        {{ formatTime(job.completed_at || job.updated_at) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';
import { PrintService } from '@/services/PrintService';

const { t } = useI18n();

interface PrintJob {
    id: number;
    job_id: string;
    asset: {
        id: number;
        asset_tag: string;
        serial_number: string;
        model_name: string;
        category: string;
        // Add minimal required Asset fields for compatibility
        asset_category_id: number;
        location_id: number;
        company_id: number;
        status: 'available' | 'assigned' | 'maintenance' | 'retired';
        condition: 'good' | 'damaged';
        purchase_date: string;
        created_at: string;
        updated_at: string;
    };
    company: {
        id: number;
        name: string;
    };
    user: {
        id: number;
        name: string;
    };
    priority: 'low' | 'normal' | 'high' | 'urgent';
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    print_data: any;
    comment?: string;
    requested_at: string;
    completed_at?: string;
    updated_at: string;
    created_at: string;
}

interface Statistics {
    pending: number;
    processing: number;
    completed_today: number;
    failed_today: number;
}

const allJobs = ref<PrintJob[]>([]);
const recentJobs = ref<PrintJob[]>([]);
const statistics = ref<Statistics>({
    pending: 0,
    processing: 0,
    completed_today: 0,
    failed_today: 0,
});

const loading = ref(false);
const loadingPrinters = ref(false);
const printerStatus = ref('Initializing...');
const selectedPrinter = ref('');
const availablePrinters = ref<string[]>([]);
const printService = PrintService.getInstance();

const pendingJobs = computed(() => 
    allJobs.value.filter(job => job.status === 'pending').sort((a, b) => {
        // Sort by priority (urgent > high > normal > low) then by created time
        const priorityOrder = { urgent: 4, high: 3, normal: 2, low: 1 };
        const aPriority = priorityOrder[a.priority] || 0;
        const bPriority = priorityOrder[b.priority] || 0;
        
        if (aPriority !== bPriority) {
            return bPriority - aPriority; // Higher priority first
        }
        
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime(); // Older first
    })
);

const processingJobs = computed(() => 
    allJobs.value.filter(job => job.status === 'processing')
);

const getPriorityVariant = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return 'destructive';
        case 'high':
            return 'secondary';
        case 'normal':
            return 'outline';
        case 'low':
            return 'secondary';
        default:
            return 'outline';
    }
};

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString();
};

const refreshJobs = async () => {
    loading.value = true;
    try {
        const [jobsResponse, recentResponse, statsResponse] = await Promise.all([
            fetch('/api/print-jobs/pending'),
            fetch('/api/print-jobs/history?limit=10'),
            fetch('/api/print-jobs/statistics')
        ]);

        if (jobsResponse.ok) {
            allJobs.value = await jobsResponse.json();
        }
        
        if (recentResponse.ok) {
            recentJobs.value = await recentResponse.json();
        }
        
        if (statsResponse.ok) {
            statistics.value = await statsResponse.json();
        }
    } catch (error) {
        console.error('Failed to refresh jobs:', error);
    } finally {
        loading.value = false;
    }
};

const processPrintJob = async (job: PrintJob) => {
    // Check if we have printers available
    if (availablePrinters.value.length === 0) {
        alert('No printers available. Please click the refresh button to load printers.');
        return;
    }

    if (!selectedPrinter.value) {
        // Try to auto-select a printer if none is selected
        const zDesignerPrinter = availablePrinters.value.find((printer: string) => 
            printer.toLowerCase().includes('zdesigner')
        );
        
        if (zDesignerPrinter) {
            selectedPrinter.value = zDesignerPrinter;
            console.log('PrintStation: Auto-selected ZDesigner printer for printing:', zDesignerPrinter);
        } else if (availablePrinters.value.length > 0) {
            selectedPrinter.value = availablePrinters.value[0];
            console.log('PrintStation: Auto-selected first available printer for printing:', availablePrinters.value[0]);
        } else {
            alert('Please select a printer first');
            return;
        }
    }

    try {
        // Mark as processing
        const response = await fetch(`/api/print-jobs/${job.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                status: 'processing',
                print_station_id: 'MANUAL_STATION_' + Date.now(),
                printer_name: selectedPrinter.value,
            }),
        });

        if (response.ok) {
            // Update local job status
            const jobIndex = allJobs.value.findIndex(j => j.id === job.id);
            if (jobIndex !== -1) {
                allJobs.value[jobIndex].status = 'processing';
            }
            
            // Print using JSPrintManager
            await printAssetLabel(job);
        }
    } catch (error) {
        console.error('Failed to process print job:', error);
        alert('Failed to process print job: ' + error);
    }
};

const printAssetLabel = async (job: PrintJob) => {
    try {
        console.log(`PrintStation: Printing label for asset ${job.asset.asset_tag} on printer ${selectedPrinter.value}`);
        
        // Use the exact same printing logic as Assets/Index.vue
        await printService.printAssetLabel(
            job.asset as any, 
            false, // Don't use default printer
            selectedPrinter.value
        );
        
        console.log(`PrintStation: Successfully printed label for asset ${job.asset.asset_tag}`);
    } catch (error) {
        console.error('PrintStation: Failed to print label:', error);
        throw error;
    }
};

const completePrintJob = async (job: PrintJob) => {
    try {
        const response = await fetch(`/api/print-jobs/${job.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                status: 'completed',
            }),
        });

        if (response.ok) {
            // Remove from active jobs
            allJobs.value = allJobs.value.filter(j => j.id !== job.id);
            statistics.value.completed_today++;
            
            // Refresh recent jobs
            refreshJobs();
        }
    } catch (error) {
        console.error('Failed to complete print job:', error);
    }
};

const failPrintJob = async (job: PrintJob) => {
    try {
        const response = await fetch(`/api/print-jobs/${job.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                status: 'failed',
                error_message: 'Print job failed',
            }),
        });

        if (response.ok) {
            // Remove from active jobs
            allJobs.value = allJobs.value.filter(j => j.id !== job.id);
            statistics.value.failed_today++;
            
            // Refresh recent jobs
            refreshJobs();
        }
    } catch (error) {
        console.error('Failed to fail print job:', error);
    }
};

const cancelPrintJob = async (job: PrintJob) => {
    try {
        const response = await fetch(`/api/print-jobs/${job.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            // Remove from jobs
            allJobs.value = allJobs.value.filter(j => j.id !== job.id);
        }
    } catch (error) {
        console.error('Failed to cancel print job:', error);
    }
};

// Simplified printer loading - direct JSPrintManager access
const loadPrintersFromJSPM = async () => {
    loadingPrinters.value = true;
    availablePrinters.value = [];
    printerStatus.value = 'Connecting to JSPrintManager...';
    
    try {
        console.log('PrintStation: Starting simple printer loading...');
        
        // Check if JSPrintManager is available in window
        if (!window.JSPM || !window.JSPM.JSPrintManager) {
            throw new Error('JSPrintManager not available in window object');
        }
        
        printerStatus.value = 'JSPrintManager found, checking connection...';
        
        // Check connection status
        const wsStatus = window.JSPM.JSPrintManager.websocket_status;
        console.log('PrintStation: WebSocket status:', wsStatus);
        
        if (wsStatus === 0) {
            // Already connected, get printers directly
            printerStatus.value = 'Connected! Getting printers...';
            const printers = await window.JSPM.JSPrintManager.getPrinters();
            console.log('PrintStation: Direct query found printers:', printers);
            
            if (printers && printers.length > 0) {
                availablePrinters.value = printers;
                printerStatus.value = `Found ${printers.length} printer(s)`;
                
                // Auto-select ZDesigner printer
                if (!selectedPrinter.value) {
                    const zDesignerPrinter = printers.find((printer: string) => 
                        printer.toLowerCase().includes('zdesigner')
                    );
                    
                    if (zDesignerPrinter) {
                        selectedPrinter.value = zDesignerPrinter;
                        console.log('PrintStation: Auto-selected ZDesigner printer:', zDesignerPrinter);
                    } else if (printers.length > 0) {
                        selectedPrinter.value = printers[0];
                        console.log('PrintStation: Auto-selected first printer:', printers[0]);
                    }
                }
            } else {
                printerStatus.value = 'Connected but no printers found';
            }
        } else {
            // Need to connect first
            printerStatus.value = 'Connecting...';
            console.log('PrintStation: Need to connect, status:', wsStatus);
            
            // Try to start connection
            window.JSPM.JSPrintManager.auto_reconnect = true;
            window.JSPM.JSPrintManager.start();
            
            // Wait a bit and try again
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            const newStatus = window.JSPM.JSPrintManager.websocket_status;
            console.log('PrintStation: After start(), new status:', newStatus);
            
            if (newStatus === 0) {
                printerStatus.value = 'Connected! Getting printers...';
                const printers = await window.JSPM.JSPrintManager.getPrinters();
                console.log('PrintStation: After connection, found printers:', printers);
                
                if (printers && printers.length > 0) {
                    availablePrinters.value = printers;
                    printerStatus.value = `Found ${printers.length} printer(s)`;
                    
                    // Auto-select ZDesigner printer
                    if (!selectedPrinter.value) {
                        const zDesignerPrinter = printers.find((printer: string) => 
                            printer.toLowerCase().includes('zdesigner')
                        );
                        
                        if (zDesignerPrinter) {
                            selectedPrinter.value = zDesignerPrinter;
                            console.log('PrintStation: Auto-selected ZDesigner printer:', zDesignerPrinter);
                        } else if (printers.length > 0) {
                            selectedPrinter.value = printers[0];
                            console.log('PrintStation: Auto-selected first printer:', printers[0]);
                        }
                    }
                } else {
                    printerStatus.value = 'Connected but no printers found';
                }
            } else {
                throw new Error(`Failed to connect to JSPrintManager. Status: ${newStatus}`);
            }
        }
        
    } catch (error) {
        console.error('PrintStation: Simple printer loading failed:', error);
        printerStatus.value = 'Failed to load printers';
    } finally {
        loadingPrinters.value = false;
        console.log('PrintStation: Loading complete. Final state:');
        console.log('- availablePrinters:', availablePrinters.value);
        console.log('- selectedPrinter:', selectedPrinter.value);
        console.log('- loadingPrinters:', loadingPrinters.value);
    }
};

// Debug JSPrintManager connection
const debugJSPrintManager = async () => {
    console.log('=== PrintStation JSPrintManager Debug ===');
    console.log('Window object:', typeof window);
    console.log('window.JSPM:', window.JSPM);
    console.log('window.JSPM.JSPrintManager:', window.JSPM?.JSPrintManager);
    console.log('Available printers:', availablePrinters.value);
    console.log('Selected printer:', selectedPrinter.value);
    console.log('Loading printers:', loadingPrinters.value);
    console.log('Printer status:', printerStatus.value);
    
    if (typeof window !== 'undefined' && window.JSPM && window.JSPM.JSPrintManager) {
        console.log('JSPrintManager websocket status:', window.JSPM.JSPrintManager.websocket_status);
        console.log('WSStatus.Open:', window.JSPM.WSStatus?.Open);
        console.log('WSStatus.Closed:', window.JSPM.WSStatus?.Closed);
        console.log('WSStatus.Blocked:', window.JSPM.WSStatus?.Blocked);
        
        // If JSPrintManager is connected (status 0), try to get printers directly
        if (window.JSPM.JSPrintManager.websocket_status === 0) {
            console.log('JSPrintManager is connected! Trying to get printers directly...');
            try {
                const directPrinters = await window.JSPM.JSPrintManager.getPrinters();
                console.log('Direct printer query result:', directPrinters);
                
                if (directPrinters && directPrinters.length > 0) {
                    availablePrinters.value = directPrinters;
                    loadingPrinters.value = false;
                    printerStatus.value = `Direct query: Found ${directPrinters.length} printer(s)`;
                    
                    // Auto-select if none selected
                    if (!selectedPrinter.value) {
                        const zDesignerPrinter = directPrinters.find((printer: string) => 
                            printer.toLowerCase().includes('zdesigner')
                        );
                        
                        if (zDesignerPrinter) {
                            selectedPrinter.value = zDesignerPrinter;
                            console.log('Debug: Auto-selected ZDesigner printer:', zDesignerPrinter);
                        } else if (directPrinters.length > 0) {
                            selectedPrinter.value = directPrinters[0];
                            console.log('Debug: Auto-selected first available printer:', directPrinters[0]);
                        }
                    }
                    
                    alert(`JSPrintManager is connected and working!\nFound ${directPrinters.length} printers.\nCheck console for details.`);
                } else {
                    alert('JSPrintManager is connected but no printers found.\nCheck console for details.');
                }
            } catch (error) {
                console.error('Direct printer query failed:', error);
                alert('JSPrintManager is connected but failed to get printers.\nError: ' + error);
            }
        } else {
            alert('JSPrintManager is available but not connected!\nWebSocket status: ' + window.JSPM.JSPrintManager.websocket_status);
        }
    } else {
        alert('JSPrintManager is NOT available!\n\nPlease check:\n1. JSPrintManager is installed\n2. JSPrintManager service is running\n3. Scripts are loaded properly');
    }
};

onMounted(async () => {
    refreshJobs();
    setInterval(refreshJobs, 10000);

    try {
        await printService.initializeJSPrintManager();
    } catch (e) {
        console.warn('PrintStation: JSPrintManager failed to initialize', e);
    }
    await loadPrintersFromJSPM();
});
</script> 