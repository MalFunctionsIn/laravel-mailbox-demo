<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds a genuinely valid (if spartan) single-page PDF receipt in memory.
 *
 * Mailbox renders attachments in the dashboard, so the demo needs a real PDF
 * rather than a .txt file wearing a .pdf extension. Hand-rolling it keeps the
 * project dependency-free — in a real app you'd reach for dompdf/snappy.
 */
final class ReceiptPdf
{
    public static function for(Order $order): string
    {
        $lines = [
            ['ACME Store', 24],
            ['Receipt for order '.$order->number, 14],
            ['', 10],
            ['Billed to: '.$order->customerName.' <'.$order->customerEmail.'>', 11],
            ['', 10],
        ];

        foreach ($order->items as $item) {
            $lines[] = [
                sprintf('%d x %s', $item['qty'], $item['name'])
                .'   '.$order->money($item['qty'] * $item['price']),
                11,
            ];
        }

        $lines[] = ['', 10];
        $lines[] = ['Subtotal: '.$order->money($order->subtotal()), 11];
        $lines[] = ['Shipping: '.$order->money($order->shipping()), 11];
        $lines[] = ['Total:    '.$order->money($order->total()), 14];

        return self::render($lines);
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $lines
     */
    private static function render(array $lines): string
    {
        $content = "BT\n";
        $y = 780;

        foreach ($lines as [$text, $size]) {
            if ($text !== '') {
                $content .= sprintf(
                    "/F1 %d Tf\n1 0 0 1 60 %d Tm\n(%s) Tj\n",
                    $size,
                    $y,
                    self::escape($text),
                );
            }

            $y -= (int) round($size * 1.6);
        }

        $content .= 'ET';

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica '
                .'/Encoding /WinAnsiEncoding >>',
            5 => '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 ".$count."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
