<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class InvoicePdf
{
    public function __construct(private InvoiceDocument $documents) {}

    public function render(Invoice $invoice): string
    {
        $html = view('invoices.document', ['d' => $this->documents->build($invoice), 'pdf' => true])->render();

        // Fonts and the render cache need a folder PHP can write to; storage/ always is.
        $dir = storage_path('app/dompdf');
        File::ensureDirectoryExists($dir);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);   // images are embedded; nothing is fetched
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [base_path('storage'), base_path('resources')]);
        $options->set('fontDir', $dir);
        $options->set('fontCache', $dir);
        $options->set('tempDir', $dir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->number).'.pdf';
    }
}
