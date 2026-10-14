SPEC: specs/U3-ticket-assignee.md
FILES CHANGED: src/Dto/AssignTicketRequest.php (new), src/Controller/TicketController.php (AgentRepository dependency; new `assign` action PATCH /{id}/assignee with MapRequestPayload REQUIRE_ALL_PROPERTIES), src/Entity/Ticket.php (toArray assignee: null or {id, name})
SCHEMA CHANGED: no
TESTS: OK (65 tests, 529 assertions)
NOTES: DTO property is `public mixed $agentId` with Assert\Type('int') instead of ?int to avoid the PHP float-to-int deprecation on {"agentId": 1.5} (suite has failOnDeprecation); same 422 result. {} yields 422 with the Serializer's missing-argument message.
