import { redirect } from 'next/navigation';

export default function FinanceIndexPage() {
  redirect('/admin/finance/invoices');
}
