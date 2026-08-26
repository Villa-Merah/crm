param(
    [string] $SourceDocx = "docs/Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.3 - Sementara.docx",
    [string] $OutputDocx = "docs/Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.4 - Sementara.docx"
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$source = (Resolve-Path (Join-Path $root $SourceDocx)).Path
$output = [System.IO.Path]::GetFullPath((Join-Path $root $OutputDocx))
$coverPath = Join-Path $root 'docs\_diagram_v1_4\cover-v1_4.png'

Copy-Item -LiteralPath $source -Destination $output -Force

function Get-VisibleChildren([string] $path) {
    @(Get-ChildItem -LiteralPath $path -Force | Where-Object {
        $name = $_.Name
        if ($_.PSIsContainer) {
            return $name -notin @('.git', '.tmp', 'docs', 'node_modules', 'tools', 'vendor') -and
                -not ($_.FullName -like '*\public\build')
        }
        return $name -notin @('.env', '.phpunit.result.cache', 'README.md', 'erd v3 - rekomendasi.drawio') -and
            $_.Extension -notin @('.docx', '.pdf', '.drawio') -and
            $name -notlike 'README*'
    } | Sort-Object @{ Expression = { -not $_.PSIsContainer } }, Name)
}

function Add-Tree([string] $path, [string] $prefix, [System.Collections.Generic.List[string]] $lines) {
    $children = Get-VisibleChildren $path
    for ($index = 0; $index -lt $children.Count; $index++) {
        $child = $children[$index]
        $last = $index -eq ($children.Count - 1)
        $branch = if ($last) { '`-- ' } else { '|-- ' }
        $suffix = if ($child.PSIsContainer) { '/' } else { '' }
        $lines.Add($prefix + $branch + $child.Name + $suffix)
        if ($child.PSIsContainer) {
            if ($child.Name -eq 'storage') {
                $storageLines = @(
                    'app/',
                    '|-- private/',
                    '`-- public/',
                    'framework/',
                    '|-- cache/',
                    '|-- sessions/',
                    '|-- testing/',
                    '`-- views/',
                    'logs/'
                )
                $nextPrefix = $prefix + $(if ($last) { '    ' } else { '|   ' })
                foreach ($storageLine in $storageLines) { $lines.Add($nextPrefix + $storageLine) }
                continue
            }
            $nextPrefix = $prefix + $(if ($last) { '    ' } else { '|   ' })
            Add-Tree $child.FullName $nextPrefix $lines
        }
    }
}

$treeLines = [System.Collections.Generic.List[string]]::new()
$treeLines.Add('sivmi.crm/')
Add-Tree $root '' $treeLines
$treeLines.Add('|-- node_modules/    [dependency npm; isi tidak diekspansi]')
$treeLines.Add('`-- vendor/          [dependency Composer; isi tidak diekspansi]')
$tree = $treeLines -join "`r`n"

$word = $null
$doc = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $doc = $word.Documents.Open($output, $false, $false)

    # Perbarui sampul dan penanda versi tanpa mengubah versi sebelumnya.
    if ($doc.InlineShapes.Count -gt 0) {
        $oldCover = $doc.InlineShapes.Item(1)
        $range = $oldCover.Range
        $width = $oldCover.Width
        $height = $oldCover.Height
        $oldCover.Delete()
        $newCover = $doc.InlineShapes.AddPicture($coverPath, $false, $true, $range)
        $newCover.LockAspectRatio = 0
        $newCover.Width = $width
        $newCover.Height = $height
    }
    foreach ($replacement in @(
        @('Sementara 1.3', 'Sementara 1.4'),
        @('v1.3', 'v1.4')
    )) {
        $find = $doc.Content.Find
        $find.ClearFormatting()
        $find.Replacement.ClearFormatting()
        $find.Execute($replacement[0], $false, $false, $false, $false, $false, $true, 1, $false, $replacement[1], 2) | Out-Null
    }

    # Sisipkan lampiran setelah Glosarium, tepat sebelum penanda akhir dokumen.
    $search = $doc.Content.Duplicate
    $search.Find.ClearFormatting()
    $search.Find.Text = '--- Akhir dokumen ---'
    $search.Find.Forward = $false
    $search.Find.Wrap = 0
    if (-not $search.Find.Execute()) { throw 'Penanda akhir dokumen tidak ditemukan.' }
    $appendixStart = $search.Paragraphs.Item(1).Range.Start

    $headingText = "LAMPIRAN A: STRUKTUR FILE DAN FOLDER PROYEK`r"
    $introText = "Struktur berikut merekam source aplikasi dan konfigurasi yang berada pada proyek. Folder docs, tools dokumentasi, berkas Word/PDF/draw.io, README, file environment aktual, cache, dan artefak build tidak dicantumkan. Dependency vendor dan node_modules tetap ditunjukkan sebagai folder, tetapi isinya tidak diekspansi.`r"
    $noteText = "Keterangan: app memuat domain dan panel; database memuat migration/seeder; resources memuat UI; tests memuat pengujian; docker memuat container; public memuat entry point dan aset publik.`r"
    $block = $headingText + $introText + $noteText + $tree + "`r"
    $insert = $doc.Range($appendixStart, $appendixStart)
    $insert.Text = $block
    $insertEnd = $insert.End

    $headingRange = $doc.Range($appendixStart, $appendixStart + $headingText.Length - 1)
    $headingRange.Style = 'Heading 1'
    $bodyStart = $appendixStart + $headingText.Length
    $bodyEnd = $insertEnd
    $bodyRange = $doc.Range($bodyStart, $bodyEnd)
    $bodyRange.Style = 'Normal'
    $treeStart = $bodyStart + $introText.Length + $noteText.Length
    $treeRange = $doc.Range($treeStart, $bodyEnd)
    $treeRange.Font.Name = 'Consolas'
    $treeRange.Font.Size = 7.5
    $treeRange.ParagraphFormat.SpaceAfter = 0
    $treeRange.ParagraphFormat.LineSpacingRule = 0

    foreach ($field in $doc.Fields) { $field.Update() | Out-Null }
    if ($doc.TablesOfContents.Count -gt 0) { $doc.TablesOfContents.Item(1).Update() | Out-Null }
    $doc.Save()
}
finally {
    if ($null -ne $doc) {
        $doc.Close($false)
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($doc) | Out-Null
    }
    if ($null -ne $word) {
        $word.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}

Write-Output $output
