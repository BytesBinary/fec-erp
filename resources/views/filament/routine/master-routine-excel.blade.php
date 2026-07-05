<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<?php echo '<?mso-application progid="Excel.Sheet"?>'; ?>

<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:html="http://www.w3.org/TR/REC-html40">

<Styles>
    <Style ss:ID="Default">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="lbl">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#CC0000"/>
        <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
        </Borders>
    </Style>
    <Style ss:ID="day_header">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>
        <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
        <Interior ss:Color="#1A237E" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
        </Borders>
    </Style>
    <Style ss:ID="period_odd">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>
        <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1"/>
        <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="period_even">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>
        <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1"/>
        <Interior ss:Color="#FFA500" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="time_hdr">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="8"/>
        <Interior ss:Color="#FFFF00" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="dept_cell">
        <Alignment ss:Vertical="Center" ss:Horizontal="Left" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#1A237E"/>
        <Interior ss:Color="#C5CAE9" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
        </Borders>
    </Style>
    <Style ss:ID="sem_cell">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>
        <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1"/>
        <Interior ss:Color="#FFA500" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="course_cell">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="lab_cell">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9"/>
        <Interior ss:Color="#FFF3E0" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="day_sep_cell">
        <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
        <Font ss:FontName="Calibri" ss:Size="9"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
</Styles>

<Worksheet ss:Name="Master Routine">
<Table>
    {{-- Column widths --}}
    <Column ss:Width="120"/>{{-- Department --}}
    <Column ss:Width="55"/>{{-- Semester --}}
    @foreach($days as $dayIndex => $dayName)
        @foreach($timeSlots as $ts)
            <Column ss:Width="65"/>
        @endforeach
    @endforeach

    {{-- Row 1: Time→ | Day→ | Day names --}}
    <Row ss:Height="20">
        <Cell ss:StyleID="lbl"><Data ss:Type="String">Time→</Data></Cell>
        <Cell ss:StyleID="lbl"><Data ss:Type="String">Day→</Data></Cell>
        @foreach($days as $dayIndex => $dayName)
            <Cell ss:StyleID="day_header" ss:MergeAcross="{{ count($timeSlots) - 1 }}">
                <Data ss:Type="String">{{ $dayName }}</Data>
            </Cell>
        @endforeach
    </Row>

    {{-- Row 2: Department | Period→ | Period numbers --}}
    <Row ss:Height="18">
        <Cell ss:StyleID="lbl"><Data ss:Type="String">Department</Data></Cell>
        <Cell ss:StyleID="lbl"><Data ss:Type="String">Period→</Data></Cell>
        @foreach($days as $dayIndex => $dayName)
            @foreach($timeSlots as $ts)
                <Cell ss:StyleID="{{ $loop->iteration % 2 === 1 ? 'period_odd' : 'period_even' }}">
                    <Data ss:Type="Number">{{ $loop->iteration }}</Data>
                </Cell>
            @endforeach
        @endforeach
    </Row>

    {{-- Row 3: (empty) | Semester | Time ranges --}}
    <Row ss:Height="22">
        <Cell ss:StyleID="lbl"><Data ss:Type="String"></Data></Cell>
        <Cell ss:StyleID="lbl"><Data ss:Type="String">Semester</Data></Cell>
        @foreach($days as $dayIndex => $dayName)
            @foreach($timeSlots as $ts)
                <Cell ss:StyleID="{{ $loop->last ? 'time_hdr' : 'time_hdr' }}">
                    <Data ss:Type="String">{{ \Carbon\Carbon::parse($ts->start_time)->format('H:i') }}-{{ \Carbon\Carbon::parse($ts->end_time)->format('H:i') }}</Data>
                </Cell>
            @endforeach
        @endforeach
    </Row>

    {{-- Data rows --}}
    @php $prevDept = null; @endphp
    @foreach($reportRows as $key => $row)
        @php
            $isNewDept = $row['dept_name'] !== $prevDept;
            if ($isNewDept) {
                $deptRowCount = collect($reportRows)->where('dept_name', $row['dept_name'])->count();
                $prevDept = $row['dept_name'];
            }
        @endphp
        <Row ss:Height="28">
            @if($isNewDept)
                <Cell ss:StyleID="dept_cell"@if($deptRowCount > 1) ss:MergeDown="{{ $deptRowCount - 1 }}"@endif>
                    <Data ss:Type="String">{{ $row['dept_name'] }}</Data>
                </Cell>
                <Cell ss:StyleID="sem_cell"><Data ss:Type="Number">{{ $row['semester'] }}</Data></Cell>
            @else
                {{-- col 1 is occupied by MergeDown — use ss:Index="2" to place semester in correct column --}}
                <Cell ss:StyleID="sem_cell" ss:Index="2"><Data ss:Type="Number">{{ $row['semester'] }}</Data></Cell>
            @endif

            @foreach($days as $dayIndex => $dayName)
                @php $skipCount = 0; @endphp
                @foreach($timeSlots as $ts)
                    @if($skipCount > 0)
                        @php $skipCount--; @endphp
                        @continue
                    @endif
                    @php
                        $cell = $row['slots'][$dayIndex][$ts->id] ?? null;
                        $span = $cell ? ($cell['lab_span'] ?? 1) : 1;
                        if ($span > 1) { $skipCount = $span - 1; }
                        $isLastInDay = $loop->remaining < $span;
                        $styleId = $span > 1 ? 'lab_cell' : ($isLastInDay ? 'day_sep_cell' : 'course_cell');
                    @endphp
                    <Cell ss:StyleID="{{ $styleId }}"@if($span > 1) ss:MergeAcross="{{ $span - 1 }}"@endif>
                        @if($cell)
                            <Data ss:Type="String">{{ $cell['course_code'] }} / {{ $cell['teacher_short'] }}</Data>
                        @else
                            <Data ss:Type="String"></Data>
                        @endif
                    </Cell>
                @endforeach
            @endforeach
        </Row>
    @endforeach

</Table>
</Worksheet>
</Workbook>
