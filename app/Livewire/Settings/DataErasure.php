<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\AccessLog;
use App\Models\Attachment;
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
    public string $search = '';

    public string $contactId = '';

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

        // Target destruktif adalah ID stabil ter-scope perusahaan, bukan nama
        // terenkripsi yang tidak unik. State publik Livewire tidak dipercaya:
        // ID divalidasi dan kepemilikan perusahaan dicek ulang pada saat aksi.
        if (! ctype_digit($this->contactId) || (int) $this->contactId < 1) {
            $this->feedback = 'ID '.strtolower(term('contact')).' harus berupa angka bulat yang valid.';
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
            DB::transaction(function () use ($companyId) {
                $targetContact = Contact::where('company_id', $companyId)
                    ->whereKey((int) $this->contactId)
                    ->first();

                if (! $targetContact) {
                    throw new LogicException(ucfirst(term('contact'))." #{$this->contactId} tidak ditemukan.");
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

                // Hapus metadata lampiran polimorfik (D-29) milik kontak dan
                // resepnya dalam transaksi yang sama. File fisik tetap di
                // Drive milik klien sendiri (D-22 BYOS) - tidak ada kontrak
                // penghapusan penyimpanan yang diotorisasi di repo ini.
                $prescriptionIds = Prescription::where('company_id', $companyId)
                    ->where('patient_contact_id', $contactId)
                    ->pluck('id');

                Attachment::where('company_id', $companyId)
                    ->where(function ($query) use ($contactId, $prescriptionIds) {
                        $query->where(function ($q) use ($contactId) {
                            $q->where('attachable_type', Contact::class)
                                ->where('attachable_id', $contactId);
                        })->orWhere(function ($q) use ($prescriptionIds) {
                            $q->where('attachable_type', Prescription::class)
                                ->whereIn('attachable_id', $prescriptionIds);
                        });
                    })
                    ->delete();

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

            $this->feedback = 'Data '.strtolower(term('contact'))." #{$this->contactId} berhasil dihapus permanen. Jejak transaksi disisakan secara anonim.";
            $this->feedbackType = 'success';
            $this->search = '';
            $this->contactId = '';
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

        return view('livewire.settings.data-erasure', [
            'contactTerm' => $contactTerm,
            'matches' => $this->contactMatches(),
        ]);
    }

    /**
     * Pencarian baca-saja untuk konteks identitas. Hasilnya hanya membantu
     * pemilik memilih ID target; ia tidak pernah menjadi target destruktif.
     */
    private function contactMatches(): array
    {
        $needle = mb_strtolower(trim($this->search));
        if (mb_strlen($needle) < 2) {
            return [];
        }

        try {
            $companyId = app(CompanyContext::class)->current();
        } catch (\Throwable) {
            return [];
        }

        if (! $companyId) {
            return [];
        }

        // Nama terenkripsi tidak bisa dicari di SQL (D-42) - filter di PHP.
        return Contact::where('company_id', $companyId)
            ->get()
            ->filter(fn (Contact $contact) => str_contains(mb_strtolower((string) $contact->name), $needle))
            ->map(fn (Contact $contact) => [
                'id' => (int) $contact->id,
                'name' => (string) $contact->name,
                'type' => (string) $contact->type,
            ])
            ->values()
            ->all();
    }
}
