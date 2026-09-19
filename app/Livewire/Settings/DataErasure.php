<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\AccessLog;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Project;
use App\Services\CompanyRoleResolver;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use LogicException;

class DataErasure extends Component
{
    public string $contactName = '';

    public string $confirmationCode = '';

    public ?string $feedback = null;

    public ?string $feedbackType = null;

    public bool $isErasing = false;

    public function erase(CompanyContext $companyContext): void
    {
        $this->feedback = null;
        $this->feedbackType = null;

        $companyId = $companyContext->current();
        if (! $companyId) {
            return;
        }

        // Owner-only server-side (QA-UI-R B.7): visibilitas tab hanya
        // mengontrol menu, bukan aksi. Aksi destruktif revalidasi peran dari
        // kepemilikan terautentikasi, bukan dari state komponen.
        abort_unless(app(CompanyRoleResolver::class)->isOwnerOfCompany($companyId), 403);

        $contactName = trim($this->contactName);
        if ($contactName === '') {
            $this->feedback = 'Nama '.strtolower(term('contact')).' harus diisi.';
            $this->feedbackType = 'error';

            return;
        }

        if (strtoupper(trim($this->confirmationCode)) !== 'YA') {
            $this->feedback = 'Ketik YA untuk mengonfirmasi penghapusan.';
            $this->feedbackType = 'error';

            return;
        }

        $this->isErasing = true;

        try {
            DB::transaction(function () use ($companyId, $contactName) {
                // Fetch all contacts and filter in PHP since name is encrypted
                $contacts = Contact::where('company_id', $companyId)->get();
                $targetContact = $contacts->firstWhere('name', $contactName);

                if (! $targetContact) {
                    throw new LogicException(ucfirst(term('contact'))." \"$contactName\" tidak ditemukan.");
                }

                $contactId = $targetContact->id;

                // Anonymize Deals
                Deal::where('company_id', $companyId)
                    ->where('contact_id', $contactId)
                    ->update(['contact_id' => null]);

                // Anonymize Bookings
                Booking::where('company_id', $companyId)
                    ->where('contact_id', $contactId)
                    ->update(['contact_id' => null]);

                // Anonymize Orders
                Order::where('company_id', $companyId)
                    ->where('contact_id', $contactId)
                    ->update(['contact_id' => null]);

                // Anonymize Projects
                Project::where('company_id', $companyId)
                    ->where('contact_id', $contactId)
                    ->update(['contact_id' => null]);

                // Hapus rekam medis (Prescriptions)
                Prescription::where('company_id', $companyId)
                    ->where('patient_contact_id', $contactId)
                    ->delete();

                // Hapus contact
                $targetContact->delete();

                AccessLog::create([
                    'company_id' => $companyId,
                    'user_id' => auth()->id(),
                    'subject_type' => Contact::class,
                    'subject_id' => $contactId,
                    'action' => 'erasure',
                    'ip' => request()->ip(),
                ]);
            });

            $this->feedback = 'Data '.strtolower(term('contact'))." \"$contactName\" berhasil dihapus permanen. Jejak transaksi disisakan secara anonim.";
            $this->feedbackType = 'success';
            $this->contactName = '';
            $this->confirmationCode = '';
        } catch (LogicException $e) {
            $this->feedback = $e->getMessage();
            $this->feedbackType = 'error';
        } catch (\Throwable $e) {
            report($e);
            $this->feedback = 'Terjadi kesalahan sistem saat menghapus data.';
            $this->feedbackType = 'error';
        } finally {
            $this->isErasing = false;
        }
    }

    public function render()
    {
        // Istilah dibaca aman: fixture/preset belum lengkap tidak boleh
        // meruntuhkan seluruh halaman Pengaturan (pola sama dengan
        // Settings::refreshTerminologyForm()).
        try {
            $contactTerm = strtolower(term('contact'));
        } catch (\Throwable) {
            $contactTerm = 'pelanggan';
        }

        return view('livewire.settings.data-erasure', ['contactTerm' => $contactTerm]);
    }
}
