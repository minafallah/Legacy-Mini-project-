# train_lora.py
# Fine-tune with LoRA using plain Transformers Trainer (no TRL).
import os, torch
from datasets import load_dataset
from transformers import (
    AutoTokenizer, AutoModelForCausalLM,
    DataCollatorForLanguageModeling, Trainer, TrainingArguments
)
from peft import LoraConfig, get_peft_model

# ---- Config ----
BASE = "Qwen/Qwen2.5-1.5B-Instruct"   # small & open; good for Mac
DATA_PATH = "train.jsonl"             # from make_jsonl.py
OUT_DIR = "./lora-out"

EPOCHS = 2
BATCH_PER_DEVICE = 1
GRAD_ACCUM_STEPS = 8
LR = 2e-4
MODEL_MAX_LEN = 256                   # keep moderate for MPS memory
WARMUP_RATIO = 0.03
LOG_STEPS = 10
SAVE_STEPS = 100
# ---------------

assert os.path.exists(DATA_PATH), f"Missing {DATA_PATH}. Run make_jsonl.py first."

use_mps = torch.backends.mps.is_available()
device = torch.device("mps" if use_mps else "cpu")
print("Device:", "MPS" if use_mps else "CPU")

# Tokenizer
tok = AutoTokenizer.from_pretrained(BASE, use_fast=True)
if tok.pad_token is None:
    tok.pad_token = tok.eos_token
tok.padding_side = "right"
tok.truncation_side = "left"
tok.model_max_length = MODEL_MAX_LEN
tok.init_kwargs["model_max_length"] = MODEL_MAX_LEN

# Dataset -> text via chat template
raw = load_dataset("json", data_files=DATA_PATH)["train"]

def to_text(ex):
    # ex["messages"] = [{"role":"user","content":...},{"role":"assistant","content":...}]
    text = tok.apply_chat_template(ex["messages"], tokenize=False, add_generation_prompt=False)
    return {"text": text}

text_ds = raw.map(to_text, remove_columns=raw.column_names)

# Tokenize with truncation; let pad at collate time
def tokenize_fn(ex):
    return tok(ex["text"], truncation=True, max_length=MODEL_MAX_LEN)

tok_ds = text_ds.map(tokenize_fn, batched=True, remove_columns=["text"], load_from_cache_file=False)

# Data collator for causal LM (creates labels from input_ids)
collator = DataCollatorForLanguageModeling(tokenizer=tok, mlm=False)

# Model
model = AutoModelForCausalLM.from_pretrained(
    BASE,
    torch_dtype=torch.float16 if use_mps else torch.float32,
    attn_implementation="sdpa",
    low_cpu_mem_usage=True
)
model = model.to(device)
model.gradient_checkpointing_enable()

# LoRA
peft_conf = LoraConfig(
    r=8,
    lora_alpha=16,
    lora_dropout=0.05,
    target_modules=["q_proj","k_proj","v_proj","o_proj"],
    bias="none",
    task_type="CAUSAL_LM"
)
model = get_peft_model(model, peft_conf)

# Trainer args
args = TrainingArguments(
    output_dir=OUT_DIR,
    num_train_epochs=EPOCHS,
    per_device_train_batch_size=BATCH_PER_DEVICE,
    gradient_accumulation_steps=GRAD_ACCUM_STEPS,
    learning_rate=LR,
    lr_scheduler_type="cosine",
    warmup_ratio=WARMUP_RATIO,
    logging_steps=LOG_STEPS,
    save_steps=SAVE_STEPS,
    save_total_limit=2,
    report_to=[],
    optim="adamw_torch",
    fp16=False,              # fp16 flag is CUDA-only; MPS runs fine with float16 tensors we set above
    bf16=False,
    remove_unused_columns=False,  # important for causal LM
)

trainer = Trainer(
    model=model,
    args=args,
    train_dataset=tok_ds,
    data_collator=collator,
)

#trainer.train()
trainer.train(resume_from_checkpoint=False)
trainer.save_model(OUT_DIR)  # saves LoRA adapter weights inside model folder
tok.save_pretrained(OUT_DIR)
print(f"Done. LoRA adapter saved in {OUT_DIR}")
