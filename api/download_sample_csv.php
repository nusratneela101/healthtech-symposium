<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_leads.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['first_name','last_name','email','company','job_title','role','segment','province','city','country','source']);
$samples = [
    ['James','Clarke','j.clarke@example.com','TD Bank','Chief Innovation Officer','CIO','Financial Institutions','Ontario','Toronto','Canada','Apollo'],
    ['Sarah','Chen','s.chen@example.com','RBC Ventures','Managing Partner','Partner','Venture Capital / Investors','Ontario','Toronto','Canada','Apollo'],
    ['Liam','Murphy','l.murphy@example.com','Wave Financial','Founder','Founder','FinTech Startups','Ontario','Toronto','Canada','Apollo'],
    ['Aisha','Patel','a.patel@example.com','BMO Financial','VP Digital Banking','VP','Financial Institutions','Quebec','Montreal','Canada','Apollo'],
    ['Chris','Walter','c.walter@example.com','IBM Canada','Head of Financial Services','Director','Technology & Solution Providers','Ontario','Toronto','Canada','Apollo'],
];
foreach ($samples as $row) {
    fputcsv($out, $row);
}
fclose($out);
