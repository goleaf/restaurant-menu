# ADR 0002: Class Livewire and presentation-only Blade

- Status: accepted
- Date: 2026-08-22

## Decision

All stateful UI uses normal class-based Livewire components with separate Blade templates. Volt and Livewire single-file route components are prohibited. Blade is a presentation boundary and contains no PHP blocks, Eloquent/service/container/facade calls, authorization, money/status calculations or business payload construction.

## Rationale and consequences

Separate typed PHP classes make state, validation, authorization, dependencies and tests discoverable. Presentation-only templates prevent hidden queries and security decisions during render. Static reuse stays in Blade/Flux components; business state remains server-side in Livewire/Actions. Architecture tests enforce the boundary.
