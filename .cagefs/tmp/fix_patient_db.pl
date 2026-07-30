use strict;
use warnings;

my $file = '/home/drarmank/source_build/dr.armankabir-main/src/frontend/src/components/PatientDashboard.tsx';
my $content = do { local $/; open my $fh, '<', $file or die $!; <$fh> };

# Fix 1: staff_auth reference on line ~1471
$content =~ s/
  \(storage\.getItem\("staff_auth"\) \? JSON\.parse\(storage\.getItem\("staff_auth"\) \|\| "\{\}"\)\.email : null\) \|\| "default"
/getDoctorEmail()/gx;

# Fix 2: IIFE pattern for doctorEmail from medicare_current_doctor + medicare_doctors_registry
# These are complex IIFEs that resolve an email from the registry
# Pattern: (() => { try { const session = storage.getItem("medicare_current_doctor"); ... })()
# Replace with just getDoctorEmail()

# Fix the IIFE that resolves doctorEmail (returns email string)
$content =~ s/\(\s*\(\s*\)\s*=>\s*\{\s*try\s*\{\s*const\s+session\s*=\s*storage\.getItem\("medicare_current_doctor"\);\s*if\s*\(!session\)\s*return\s*"default";\s*const\s+registry:\s*Array<\{id:\s*string;\s*email:\s*string\}>\s*=\s*JSON\.parse\(\s*storageAdapter\.getItem\("medicare_doctors_registry"\)\s*\|\|\s*"\[\]",\s*\);\s*return\s*\(\s*registry\.find\(\(d\)\s*=>\s*d\.id\s*===\s*session\)\s*\?\?\.\s*email\s*\?\?\s*"default"\s*\);\s*\}\s*catch\s*\{\s*return\s*"default";\s*\}\s*\}\)\(\)/getDoctorEmail()/g;

print "Fixed PatientDashboard.tsx\n";

open my $fh_out, '>', $file or die $!;
print $fh_out $content;
close $fh_out;
