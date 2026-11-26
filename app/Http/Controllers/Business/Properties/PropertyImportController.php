<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\ComplexAmenity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Bulk property import from the spreadsheet template, the template download, and the portfolio export. */
class PropertyImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Bulk-create property drafts from a spreadsheet. Each row is validated like the draft form;
     * nothing is saved unless every row is valid. Imported properties start in draft setup.
     */
    public function complex_upload(Request $request)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'import'), 403);
        $ownerId = business_owner_id();

        $file = collect($request->allFiles())->flatten()->first();
        if (!$file || !in_array(strtolower($file->getClientOriginalExtension()), ['xls', 'xlsx', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Upload the Excel template (.xlsx, .xls or .csv).'], 422);
        }

        $inventory = app(\App\Services\Properties\ComplexInventoryService::class);
        $rows = $inventory->readSheet($file->getRealPath());
        if (!$rows) {
            return response()->json(['success' => false, 'message' => 'The file has no property rows.'], 422);
        }
        $errors = [];
        $clean = [];
        foreach ($rows as $line => $row) {
            $row['composition'] = strtolower((string) ($row['composition'] ?? ''));
            $row['principal'] = in_array(strtolower((string) ($row['principal'] ?? 'yes')), ['yes', 'y', '1', 'true', 'owner'], true) ? 1 : 0;
            $validator = Validator::make($row, [
                'title' => 'required|string|max:255', 'ref_code' => 'required|string|max:255',
                'email' => 'required|email|max:255', 'mobile' => 'required|max:50',
                'county' => 'required|string|max:255', 'county_specific' => 'required|string|max:255',
                'location_description' => 'required|string|max:5000', 'description' => 'required|string|max:10000',
                'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
                'composition' => ['required', function ($attribute, $value, $fail) {
                    if (!\App\Services\Properties\ComplexInventoryService::kindsFromText($value)) $fail('composition must be apartments, houses and/or commercial (e.g. "houses" or "apartments, commercial").');
                }], 'class' => 'nullable|string|max:100',
            ]);
            if ($validator->fails()) {
                $errors[] = 'Row ' . $line . ': ' . implode(' ', $validator->errors()->all());
                continue;
            }
            $clean[] = $row;
        }
        if ($errors) {
            return response()->json(['success' => false, 'message' => 'No properties were imported. ' . implode(' ', array_slice($errors, 0, 10)), 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($clean, $ownerId, $inventory) {
            foreach ($clean as $row) {
                $complex = Complex::create([
                    'user_id' => $ownerId,
                    'title' => $row['title'], 'ref_code' => (string) $row['ref_code'], 'email' => $row['email'], 'mobile' => (string) $row['mobile'],
                    'county' => $row['county'], 'county_specific' => $row['county_specific'],
                    'location_description' => $row['location_description'], 'description' => $row['description'],
                    'latitude' => $row['latitude'], 'longitude' => $row['longitude'], 'principal' => $row['principal'],
                    'type' => \App\Services\Properties\ComplexInventoryService::typeFor(\App\Services\Properties\ComplexInventoryService::kindsFromText($row['composition'])),
                    'class' => $row['class'] ?: 'Regular', 'available' => 0, 'setup_status' => 'draft',
                    'slug' => Str::slug($row['title']) . '-' . Str::lower(Str::random(8)),
                ]);
                $inventory->audit($complex, 'imported', null, $complex->title);
                // Limited team members manage the properties they import.
                app(\App\Services\Access\PropertyAccess::class)->claimCreated($complex->id);
            }
        });

        return response()->json(['success' => true, 'count' => count($clean), 'message' => count($clean) . ' properties imported as drafts. Open each one to complete setup.']);
    }
    /** Template for the property import, generated so it always matches the importer. */
    public function downloadComplex()
    {
        $path = app(\App\Services\Properties\ComplexInventoryService::class)->template(
            ['title', 'ref_code', 'email', 'mobile', 'county', 'county_specific', 'location_description', 'description', 'latitude', 'longitude', 'composition', 'class', 'principal'],
            ['Riverside Apartments', 'RVS-001', 'manager@example.com', '+254700000000', 'Nairobi', 'Westlands', 'Gate B, Riverside Drive', 'Six-storey residential block', -1.2655, 36.8047, 'residential', 'Regular', 'yes'],
            [
                'One row per property. Every column except class and principal is required.',
                'composition: apartments, houses and/or commercial, separated by commas (e.g. "houses" or "apartments, commercial"). residential and combined still work.',
                'latitude / longitude: decimal degrees of the property entrance.',
                'principal: yes if the business owns the property, no if it manages it for an owner.',
                'Imported properties start as drafts: add units, facilities and documents in setup, then confirm.',
            ]
        );
        return response()->download($path, 'erty-property-import-template.xlsx')->deleteFileAfterSend(true);
    }
    /**
     * Download complexes data as Excel file
     *
     * @return \Illuminate\Http\Response
     */
    public function dataDownloadComplexes()
    {
        try {
            $user_id = business_owner_id();
            
            // Get all complexes for the authenticated user
            $complexes = Complex::select([
                'complexes.id', 
                'complexes.ref_code',
                'complexes.title',
                'complexes.slug', 
                'complexes.mobile',
                'complexes.email',
                'complexes.longitude',
                'complexes.latitude',
                'complexes.available',
                'complexes.description',
                'complexes.principal',
                'complexes.type',
                'complexes.class',
                'complexes.county',
                'complexes.county_specific',
                'complexes.location_description',
                'complexes.created_at',
                'complexes.updated_at'
            ])
            ->where('complexes.user_id', $user_id)
            ->leftJoinSub(\App\Models\ComplexExpense::query()->select('id', 'complex_id', 'amount', 'user_id'), 'complex_expenses', 'complexes.id', '=', 'complex_expenses.complex_id')
            ->addSelect(DB::raw('SUM(COALESCE(complex_expenses.amount, 0)) as total_expenses'))
            ->groupBy([
                'complexes.id',
                'complexes.ref_code',
                'complexes.title',
                'complexes.slug', 
                'complexes.mobile',
                'complexes.email',
                'complexes.longitude',
                'complexes.latitude',
                'complexes.available',
                'complexes.description',
                'complexes.principal',
                'complexes.type',
                'complexes.class',
                'complexes.county',
                'complexes.county_specific',
                'complexes.location_description',
                'complexes.created_at',
                'complexes.updated_at'
            ])
            ->orderBy('complexes.created_at', 'desc')
            ->get();

            // Get all amenities for the user's complexes
            $amenities = ComplexAmenity::select([
                'complex_amenities.*',
                'complexes.ref_code as complex_ref_code',
                'complexes.title as complex_title'
            ])
            ->join('complexes', 'complex_amenities.complex_id', '=', 'complexes.id')
            ->where('complexes.user_id', $user_id)
            ->orderBy('complexes.ref_code')
            ->orderBy('complex_amenities.amenity_type')
            ->get();

            if ($complexes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No complexes found to download.'
                ], 404);
            }
    
            // Create new Spreadsheet object
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            
            // ===== COMPLEXES SHEET =====
            $complexSheet = $spreadsheet->getActiveSheet();
            $complexSheet->setTitle('Complexes');
    
            // Complexes headers
            $complexHeaders = [
                'A1' => 'Reference Code',
                'B1' => 'Title',
                'C1' => 'Mobile',
                'D1' => 'Email',
                'E1' => 'Longitude',
                'F1' => 'Latitude',
                'G1' => 'Available Units',
                'H1' => 'Description',
                'I1' => 'Principal',
                'J1' => 'Type',
                'K1' => 'Class',
                'L1' => 'County',
                'M1' => 'County Specific',
                'N1' => 'Location Description',
                'O1' => 'Total Expenses (KES)',
                'P1' => 'Created Date',
                'Q1' => 'Last Updated'
            ];
    
            // Apply complexes headers
            foreach ($complexHeaders as $cell => $header) {
                $complexSheet->setCellValue($cell, $header);
            }
    
            // Style complexes headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ];
    
            $complexSheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
    
            // Add complexes data rows
            $row = 2;
            foreach ($complexes as $complex) {
                $complexSheet->setCellValue('A' . $row, $complex->ref_code ?? '-');
                $complexSheet->setCellValue('B' . $row, $complex->title ?? '-');
                $complexSheet->setCellValue('C' . $row, $complex->mobile ?? '-');
                $complexSheet->setCellValue('D' . $row, $complex->email ?? '-');
                $complexSheet->setCellValue('E' . $row, $complex->longitude ?? '-');
                $complexSheet->setCellValue('F' . $row, $complex->latitude ?? '-');
                $complexSheet->setCellValue('G' . $row, $complex->available ?? '0');
                $complexSheet->setCellValue('H' . $row, $complex->description ?? '-');
                $complexSheet->setCellValue('I' . $row, $complex->principal ?? '-');
                $complexSheet->setCellValue('J' . $row, $complex->type ?? '-');
                $complexSheet->setCellValue('K' . $row, $complex->class ?? '-');
                $complexSheet->setCellValue('L' . $row, $complex->county ?? '-');
                $complexSheet->setCellValue('M' . $row, $complex->county_specific ?? '-');
                $complexSheet->setCellValue('N' . $row, Str::limit($complex->location_description ?? '-', 100));
                $complexSheet->setCellValue('O' . $row, number_format($complex->total_expenses ?? 0, 2));
                $complexSheet->setCellValue('P' . $row, $complex->created_at ? $complex->created_at->format('Y-m-d H:i:s') : '-');
                $complexSheet->setCellValue('Q' . $row, $complex->updated_at ? $complex->updated_at->format('Y-m-d H:i:s') : '-');
                $row++;
            }
    
            // Auto-size columns for complexes sheet
            foreach (range('A', 'Q') as $col) {
                $complexSheet->getColumnDimension($col)->setAutoSize(true);
            }
    
            // ===== AMENITIES SHEET =====
            $amenitiesSheet = $spreadsheet->createSheet();
            $amenitiesSheet->setTitle('Amenities');
    
            // Amenities headers
            $amenityHeaders = [
                'A1' => 'Complex Reference',
                'B1' => 'Complex Title',
                'C1' => 'Amenity Type',
                'D1' => 'Parking Slots',
                'E1' => 'Parking IDs',
                'F1' => 'Garbage Frequency',
                'G1' => 'Internet Provider',
                'H1' => 'Internet Speed',
                'I1' => 'Security Type',
                'J1' => 'Water Type',
                'K1' => 'Gym Description',
                'L1' => 'Elevator Count',
                'M1' => 'CCTV Coverage',
                'N1' => 'Cleaning Schedule',
                'O1' => 'Backup Type',
                'P1' => 'Conference Capacity',
                'Q1' => 'Loading Bay Size',
                'R1' => 'Created Date',
                'S1' => 'Last Updated'
            ];
    
            // Apply amenities headers
            foreach ($amenityHeaders as $cell => $header) {
                $amenitiesSheet->setCellValue($cell, $header);
            }
    
            // Style amenities headers
            $amenitiesHeaderStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '28a745'] // Green color for amenities
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ];
    
            $amenitiesSheet->getStyle('A1:S1')->applyFromArray($amenitiesHeaderStyle);
    
            // Add amenities data rows
            $amenityRow = 2;
            foreach ($amenities as $amenity) {
                $amenitiesSheet->setCellValue('A' . $amenityRow, $amenity->complex_ref_code ?? '-');
                $amenitiesSheet->setCellValue('B' . $amenityRow, $amenity->complex_title ?? '-');
                $amenitiesSheet->setCellValue('C' . $amenityRow, ucwords(str_replace('_', ' ', $amenity->amenity_type ?? '-')));
                $amenitiesSheet->setCellValue('D' . $amenityRow, $amenity->parking_slots ?? '-');
                $amenitiesSheet->setCellValue('E' . $amenityRow, $amenity->parking_ids ?? '-');
                $amenitiesSheet->setCellValue('F' . $amenityRow, $amenity->garbage_frequency ?? '-');
                $amenitiesSheet->setCellValue('G' . $amenityRow, $amenity->internet_provider ?? '-');
                $amenitiesSheet->setCellValue('H' . $amenityRow, $amenity->internet_speed ?? '-');
                $amenitiesSheet->setCellValue('I' . $amenityRow, $amenity->security_type ?? '-');
                $amenitiesSheet->setCellValue('J' . $amenityRow, $amenity->water_type ?? '-');
                $amenitiesSheet->setCellValue('K' . $amenityRow, $amenity->gym_description ?? '-');
                $amenitiesSheet->setCellValue('L' . $amenityRow, $amenity->elevator_count ?? '-');
                $amenitiesSheet->setCellValue('M' . $amenityRow, $amenity->cctv_coverage ?? '-');
                $amenitiesSheet->setCellValue('N' . $amenityRow, $amenity->cleaning_schedule ?? '-');
                $amenitiesSheet->setCellValue('O' . $amenityRow, $amenity->backup_type ?? '-');
                $amenitiesSheet->setCellValue('P' . $amenityRow, $amenity->conference_capacity ?? '-');
                $amenitiesSheet->setCellValue('Q' . $amenityRow, $amenity->loading_bay_size ?? '-');
                $amenitiesSheet->setCellValue('R' . $amenityRow, $amenity->created_at ? $amenity->created_at->format('Y-m-d H:i:s') : '-');
                $amenitiesSheet->setCellValue('S' . $amenityRow, $amenity->updated_at ? $amenity->updated_at->format('Y-m-d H:i:s') : '-');
                $amenityRow++;
            }
    
            // Auto-size columns for amenities sheet
            foreach (range('A', 'S') as $col) {
                $amenitiesSheet->getColumnDimension($col)->setAutoSize(true);
            }
    
            // Add summary information
            $summaryRow = $amenityRow + 2;
            $amenitiesSheet->setCellValue('A' . $summaryRow, 'Summary:');
            $amenitiesSheet->setCellValue('A' . ($summaryRow + 1), 'Total Complexes: ' . $complexes->count());
            $amenitiesSheet->setCellValue('A' . ($summaryRow + 2), 'Total Amenities: ' . $amenities->count());
            
            // Style summary
            $amenitiesSheet->getStyle('A' . $summaryRow . ':A' . ($summaryRow + 2))->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => '333333']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'f8f9fa']
                ]
            ]);
    
            // Safe metadata handling
            $user = auth()->user();
            $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if (empty($creatorName)) {
                $creatorName = $user->email ?? config('app.name') . ' user';
            }
    
            $businessName = 'Unknown Business';
            if ($user && $user->business && $user->business->business_name) {
                $businessName = $user->business->business_name;
            }
    
            // Add metadata
            $spreadsheet->getProperties()
                ->setCreator($creatorName)
                ->setTitle('Complexes & Amenities Export - ' . $businessName)
                ->setDescription('Export of complexes and amenities data from ' . config('app.name'))
                ->setCreated(time());
    
            // Create filename
            $filename = 'complexes_with_amenities_export_' . date('Y-m-d_H-i-s') . '.xlsx';
    
            // Create writer and save to temporary location
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'complexes_export');
            $writer->save($tempFile);
    
            return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    
        } catch (\Exception $e) {
            Log::error('Error downloading complexes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate complexes export. Please try again.'
            ], 500);
        }
    }
}
