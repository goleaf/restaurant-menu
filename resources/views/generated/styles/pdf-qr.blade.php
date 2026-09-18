/* Generated from resources/scss/pdf-qr.scss. Do not edit. */
@page {
  size: A4;
  margin: 8mm;
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
  width: auto;
  margin: 0 auto;
  border-collapse: separate;
  border-spacing: 4mm;
  table-layout: fixed;
}

.slot {
  width: 76mm;
  padding: 0;
  vertical-align: top;
  word-wrap: break-word;
}

.label {
  width: 67mm;
  min-height: 95mm;
  padding: 4mm;
  border-width: 0.5mm;
  border-style: solid;
  text-align: center;
  page-break-inside: avoid;
  vertical-align: top;
}

.brand {
  margin: 0 0 3mm;
  font-size: 12pt;
  font-weight: 700;
}

.instruction {
  margin: 0 0 4mm;
  font-size: 10pt;
}

.qr {
  width: 48mm;
  height: 48mm;
}

.code {
  margin-top: 3mm;
  font-size: 12pt;
  font-weight: 700;
  letter-spacing: 1pt;
}

.table-number {
  word-wrap: break-word;
  margin-top: 2mm;
  font-size: 10pt;
  font-weight: 700;
}

[data-qr-preset=minimal] {
  color: #0a0a0a;
}
[data-qr-preset=minimal] .label {
  border-color: #0a0a0a;
  background: #ffffff;
}
[data-qr-preset=minimal] .brand {
  color: #0a0a0a;
}

[data-qr-preset=classic] {
  color: #171717;
}
[data-qr-preset=classic] .label {
  border-color: #4a3728;
  background: #fffdf5;
}
[data-qr-preset=classic] .brand {
  color: #4a3728;
}

[data-qr-preset=restaurant] {
  color: #171717;
}
[data-qr-preset=restaurant] .label {
  border-color: #b91c1c;
  background: #fffafa;
}
[data-qr-preset=restaurant] .brand {
  color: #b91c1c;
}

[data-qr-preset=bar] {
  color: #111827;
}
[data-qr-preset=bar] .label {
  border-color: #3f2a56;
  background: #fafafa;
}
[data-qr-preset=bar] .brand {
  color: #3f2a56;
}

[data-qr-preset=hotel] {
  color: #0f172a;
}
[data-qr-preset=hotel] .label {
  border-color: #0369a1;
  background: #f8fcff;
}
[data-qr-preset=hotel] .brand {
  color: #0369a1;
}

[data-qr-preset=premium] {
  color: #0a0a0a;
}
[data-qr-preset=premium] .label {
  border-color: #0a0a0a;
  background: #ffffff;
}
[data-qr-preset=premium] .brand {
  color: #9a6b12;
}
