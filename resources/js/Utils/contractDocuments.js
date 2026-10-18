/**
 * Mirror of App\Support\ContractDocumentRules — keep both in sync.
 */
export const ContractDocumentRules = {
  MAX_BYTES: 5 * 1024 * 1024 * 1024,

  EXTENSIONS: [
    'mp4', 'mov', 'm4v', 'webm', 'avi',
    'pdf', 'doc', 'docx',
    'xls', 'xlsx',
    'jpg', 'jpeg', 'png',
  ],

  ACCEPT: '.mp4,.mov,.m4v,.webm,.avi,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png',

  HUMAN_MAX_SIZE: '5 GB',
};
