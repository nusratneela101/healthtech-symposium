<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_leads.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['first_name','last_name','email','company','job_title','role','segment','province','city','country','source']);
$samples = [
    ['James','Clarke','j.clarke@example.com','TD Health','Chief Innovation Officer','CIO','Health IT & Digital Health','Ontario','Toronto','Canada','Apollo'],
    ['Sarah','Chen','s.chen@example.com','RBC Ventures','Managing Partner','Partner','Venture Capital / Investors','Ontario','Toronto','Canada','Apollo'],
    ['Liam','Murphy','l.murphy@example.com','Wave Health','Founder','Founder','Fintech Startups','Ontario','Toronto','Canada','Apollo'],
    ['Aisha','Patel','a.patel@example.com','BMO Health','VP Digital Health','VP','Healthcare Providers','Quebec','Montreal','Canada','Apollo'],
    ['Chris','Walter','c.walter@example.com','IBM Canada','Head of Healthcare Services','Director','Health IT & Digital Health','Ontario','Toronto','Canada','Apollo'],
];
foreach ($samples as $row) {
    fputcsv($out, $row);
}
fclose($out);
