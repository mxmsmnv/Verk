<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Verk.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/Traits/VerkMcpProviderTrait.php');
$checks = [str_contains($module, "'mcpProvider' => true"), str_contains($trait, "'verk_status'"), str_contains($trait, "'private_records_exposed' => false"), str_contains($trait, "'write_tools' => false"), str_contains($trait, "'additionalProperties' => false")];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Verk MCP provider contract failed.\n"); exit(1); }
echo "Verk MCP provider contract passed.\n";
