/* Generated from resources/scss/pdf-qr.scss. Do not edit. */
@page {
  margin: 10mm;
}
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: "DejaVu Sans", sans-serif;
  font-size: 11pt;
}

.sheet {
  width: 100%;
  border-collapse: separate;
  border-spacing: 6mm;
  table-layout: fixed;
}

.label {
  width: 50%;
  padding: 7mm;
  border-width: 1.5pt;
  border-style: solid;
  text-align: center;
  page-break-inside: avoid;
  vertical-align: top;
}

.brand {
  margin: 0 0 3mm;
  font-size: 15pt;
  font-weight: 700;
}

.instruction {
  margin: 0 0 4mm;
  font-size: 10pt;
}

.qr {
  width: 55mm;
  height: 55mm;
}

.code {
  margin-top: 3mm;
  font-size: 14pt;
  font-weight: 700;
  letter-spacing: 1pt;
}

.table-number {
  margin-top: 2mm;
  font-size: 11pt;
  font-weight: 700;
}

[data-qr-preset=minimal] {
  color: #18181b;
}
[data-qr-preset=minimal] .label {
  border-color: #18181b;
  background: #ffffff;
}
[data-qr-preset=minimal] .brand {
  color: #18181b;
}

[data-qr-preset=classic] {
  color: #111827;
}
[data-qr-preset=classic] .label {
  border-color: #64748b;
  background: #f8fafc;
}
[data-qr-preset=classic] .brand {
  color: #1f2937;
}

[data-qr-preset=restaurant] {
  color: #431407;
}
[data-qr-preset=restaurant] .label {
  border-color: #fb923c;
  background: #fff7ed;
}
[data-qr-preset=restaurant] .brand {
  color: #9f2d15;
}

[data-qr-preset=bar] {
  color: #083344;
}
[data-qr-preset=bar] .label {
  border-color: #06b6d4;
  background: #ecfeff;
}
[data-qr-preset=bar] .brand {
  color: #164e63;
}

[data-qr-preset=hotel] {
  color: #27272a;
}
[data-qr-preset=hotel] .label {
  border-color: #a1a1aa;
  background: #fafafa;
}
[data-qr-preset=hotel] .brand {
  color: #3f3f46;
}

[data-qr-preset=premium] {
  color: #422006;
}
[data-qr-preset=premium] .label {
  border-color: #d97706;
  background: #fffbeb;
}
[data-qr-preset=premium] .brand {
  color: #713f12;
}
