# PHPCLI-AI

A CLI tool that generates a text-based dump of source files or a directory tree structure — ideal for processing by AI systems.

## 🔧 Installation

```bash
git clone https://github.com/jschwind/phpcli-ai.git
cd phpcli-ai
chmod +x runAIProject.sh
```

Optionally, add `runAIProject.sh` to your system’s `PATH`. For example, on Arch/Manjaro:

```bash
sudo ln -s $(pwd)/runAIProject.sh /usr/local/bin/runAIProject
```

## 🚀 Usage

```bash
runAIProject [OUTPUT_FILENAME] [--tree] [--config=PATH_TO_CONFIG]
```

By default, this command generates a text dump of the current directory’s source files and saves it to `ai.txt`.

### Options

* `OUTPUT_FILENAME` (optional): Custom name for the output file (default: `ai.txt`)
* `--tree` (optional): Outputs a directory tree instead of dumping file contents
* `--config=...` (optional): Use a specific `ai.json` configuration file

## 🧠 `ai.json` Configuration

You can control which files and folders are included or excluded by providing an `ai.json` file in your project’s root directory. This will override the default rules.

### Structure of `ai.json`

```json
{
  "exclude": {
    "extensions": ["log", "png", "jpg"],
    "folders": ["vendor", "node_modules", "tests"],
    "filenames": ["README.md", ".env"]
  },
  "include": {
    "extensions": [],
    "folders": [],
    "filenames": []
  }
}
```

### Priority

If `ai.json` is present and readable, its values will override the default ignore lists.

## 📦 Examples

```bash
runAIProject
```

Generates a file dump and writes it to `ai.txt`.

```bash
runAIProject ai2.txt
```

Same as above, but writes to `ai2.txt`.

```bash
runAIProject --tree
```

Outputs a directory tree structure to `ai.txt`.

```bash
runAIProject ai2.txt --tree
```

Outputs a directory tree structure to `ai2.txt`.

```bash
runAIProject --config=./config/ai-custom.json
```

Uses a custom configuration to control what is included or excluded.