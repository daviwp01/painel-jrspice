import { reactive } from 'vue';
import { toast } from '@/Stores/ToastStore';
import { ContractDocumentRules } from '@/Utils/contractDocuments';

const MAX_BYTES = 5 * 1024 * 1024 * 1024; // 5 GB

export const csrfToken = () => {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Upload state for contract documents.
 *
 * It deliberately lives outside any component: the app is an Inertia SPA, so
 * navigating between pages does not unload the window and the XHR keeps
 * running. Closing the tab is the only way to lose an upload — the state is
 * reported back when the user returns.
 */
export const contractUploads = reactive({
  items: [],

  /**
   * @param {{id:number, contract_number?:string}} contract
   * @param {File} file
   * @returns {Promise<void>}
   */
  start(contract, file) {
    const contractId = Number(contract.id);
    const key = `${contractId}-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    if (file.size > MAX_BYTES) {
      toast.add(`${file.name} tem ${formatSize(file.size)}. O limite é de 5 GB por arquivo.`, 'error');
      return Promise.resolve();
    }

    const allowed = ContractDocumentRules.EXTENSIONS;
    const extension = (file.name.split('.').pop() || '').toLowerCase();

    if (!allowed.includes(extension)) {
      toast.add(`Formato .${extension} não é aceito. Envie ${allowed.join(', ').toUpperCase()}.`, 'error');
      return Promise.resolve();
    }

    const entry = reactive({
      key,
      contractId,
      contractNumber: contract.contract_number || contract.id,
      name: file.name,
      size: file.size,
      progress: 0,
      state: 'uploading',
      message: null,
    });

    this.items.unshift(entry);

    return new Promise((resolve) => {
      const form = new FormData();
      form.append('file', file);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', route('contracts.documents.store', contract.id));
      xhr.timeout = 3 * 60 * 60 * 1000; // 5 GB on a slow link
      xhr.setRequestHeader('X-XSRF-TOKEN', csrfToken());
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          entry.progress = Math.min(100, Math.round((event.loaded / event.total) * 100));
        }
      };

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          entry.progress = 100;
          entry.state = 'done';
          this.revision += 1;
          this.lastContractId = contractId;
          setTimeout(() => this.dismiss(entry.key), 2500);
        } else {
          entry.state = 'error';
          entry.message = parseErrorMessage(xhr);
        }
        resolve();
      };

      xhr.onerror = () => {
        entry.state = 'error';
        entry.message = 'Falha na conexão durante o envio.';
        resolve();
      };

      xhr.ontimeout = () => {
        entry.state = 'error';
        entry.message = 'O envio excedeu o tempo limite. Tente novamente.';
        resolve();
      };

      xhr.onabort = () => {
        entry.state = 'cancelled';
        entry.message = 'Envio cancelado.';
        resolve();
        setTimeout(() => this.dismiss(entry.key), 1500);
      };

      entry.xhr = xhr;
      xhr.send(form);
    });
  },

  cancel(key) {
    const entry = this.items.find((item) => item.key === key);
    if (entry?.xhr) {
      entry.xhr.abort();
      return true;
    }
    return false;
  },

  dismiss(key) {
    this.items = this.items.filter((item) => item.key !== key);
  },

  clearContract(contractId) {
    this.items = this.items.filter((item) => item.contractId !== contractId || item.state === 'uploading');
  },

  forContract(contractId) {
    const id = Number(contractId);
    return this.items.filter((item) => item.contractId === id);
  },

  activeCount() {
    return this.items.filter((item) => item.state === 'uploading').length;
  },
});

contractUploads.revision = 0;
contractUploads.lastContractId = null;

function parseErrorMessage(xhr) {
  try {
    const payload = JSON.parse(xhr.responseText);
    if (payload?.errors?.file?.length) return payload.errors.file[0];
    if (payload?.message) return payload.message;
  } catch {
    /* resposta não é JSON */
  }

  if (xhr.status === 413) return 'Arquivo maior que o limite permitido pelo servidor.';
  if (xhr.status === 419) return 'Sessão expirada. Recarregue a página e tente novamente.';

  return `Falha no envio (HTTP ${xhr.status || 'sem resposta'}).`;
}

export function formatSize(bytes) {
  if (!bytes) return '0 B';

  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

  return `${(bytes / 1024 ** index).toFixed(index ? 1 : 0)} ${units[index]}`;
}
