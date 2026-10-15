SPEC: D:\Sandbox\yt-30-10-2026\ticketflow\specs\B2-create-ticket-missing-fields.md
FILES CHANGED:
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Controller\TicketController.php (`create()` now takes `#[MapRequestPayload(mapWhenEmpty: true)] CreateTicketRequest $payload` and no longer calls json_decode. Nothing else in the file changed.)
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Dto\CreateTicketRequest.php (new; readonly DTO with nullable `title`, `description` and `priority` that default to null, each with `#[Assert\NotBlank]`)
- D:\Sandbox\yt-30-10-2026\ticketflow\src\EventListener\TicketValidationExceptionListener.php (new; `#[AsEventListener]` on kernel.exception. It only acts on route `ticket_create` when an HttpException wraps a ValidationFailedException, and returns 422 `{"errors":{"<field>":"<first message>"}}`)
- D:\Sandbox\yt-30-10-2026\ticketflow\composer.json, D:\Sandbox\yt-30-10-2026\ticketflow\composer.lock, D:\Sandbox\yt-30-10-2026\ticketflow\symfony.lock (from `tools/composer require symfony/property-access:8.1.* symfony/property-info:8.1.*`, per D3=A)
- D:\Sandbox\yt-30-10-2026\ticketflow\config\packages\property_info.yaml (new; created by the property-info Flex recipe)
SCHEMA CHANGED: no (no migration)
TESTS: OK (16 tests, 105 assertions). This includes the tester's D:\Sandbox\yt-30-10-2026\ticketflow\tests\Api\B2CreateTicketMissingFieldsTest.php, and the existing tests pass with no changes.
NOTES:
- DECISIONS: D1=A, D2=B, D3=A.
- `mapWhenEmpty: true` makes an empty request body produce the same three NotBlank errors as `{}` (AC5).
- `composer require` also wrote `symfony/type-info` into composer.lock. property-info requires it, so it is not a direct dependency.
- A non-empty unknown priority such as "bogus" still returns 201, because only NotBlank is checked; that validation belongs to F1. Values of the wrong JSON type and malformed JSON are out of scope, and their behaviour (the resolver's default responses) was not tested.
- I did not touch any test class.
