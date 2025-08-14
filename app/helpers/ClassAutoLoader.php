<?php

namespace app\helpers;

class ClassAutoLoader
{
    /**
     * Autoload classes that implement a specific interface from a given path.
     *
     * @param string $path      the directory path to search for classes
     * @param string $interface the interface that the classes must implement
     *
     * @return array an array of instantiated objects that implement the specified interface
     */
    public static function autoloadClasses(string $path, string $interface): array
    {
        $objects = [];

        foreach (glob("{$path}/*.php") as $file) {
            // Capture classes declared before the require
            $beforeClasses = get_declared_classes();

            require_once $file;
            // Capture classes declared after the require
            // This allows us to find new classes that were loaded
            $afterClasses = get_declared_classes();
            // Identifies only the new loaded classes
            $newClasses = array_diff($afterClasses, $beforeClasses);

            foreach ($newClasses as $class) {
                $reflection = new \ReflectionClass($class);
                if ($reflection->implementsInterface($interface) && !$reflection->isAbstract()) {
                    $objects[] = $reflection->newInstanceArgs();
                }
            }
        }

        return $objects;
    }
}
