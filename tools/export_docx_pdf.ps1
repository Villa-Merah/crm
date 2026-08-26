param(
    [Parameter(Mandatory = $true)][string]$InputDocx,
    [Parameter(Mandatory = $true)][string]$OutputPdf,
    [switch]$UpdateFields,
    [switch]$SaveUpdated
)

$word = $null
$document = $null

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($InputDocx, $false, (-not $SaveUpdated))

    if ($UpdateFields) {
        foreach ($story in $document.StoryRanges) {
            $range = $story
            while ($null -ne $range) {
                if ($range.Fields.Count -gt 0) {
                    [void]$range.Fields.Update()
                }
                $range = $range.NextStoryRange
            }
        }
        if ($document.TablesOfContents.Count -gt 0) {
            foreach ($toc in $document.TablesOfContents) {
                [void]$toc.Update()
            }
        }
        if ($SaveUpdated) {
            $document.Save()
        }
    }

    $document.ExportAsFixedFormat($OutputPdf, 17)
}
finally {
    if ($null -ne $document) {
        try { $document.Close($false) } catch { }
        try { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($document) } catch { }
    }
    if ($null -ne $word) {
        try { $word.Quit() } catch { }
        try { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) } catch { }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
