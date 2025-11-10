#!/usr/bin/env python3
import argparse, torch
from transformers import AutoModelForCausalLM, AutoTokenizer
from peft import PeftModel

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", required=True, help="Base model id/path (e.g. Qwen/Qwen2.5-1.5B-Instruct)")
    ap.add_argument("--adapter", required=True, help="LoRA folder (adapter_config.json + adapter_model.safetensors)")
    ap.add_argument("--out", default="merged-model", help="Output dir")
    ap.add_argument("--force-cpu", action="store_true", help="Merge on CPU (safest on macOS)")
    args = ap.parse_args()

    # Choose device & dtype
    use_mps = (not args.force_cpu) and torch.backends.mps.is_available()
    device = torch.device("mps" if use_mps else "cpu")
    base_dtype = torch.float16 if use_mps else torch.float32

    print(f"Loading base: {args.base} on {device} (dtype={base_dtype})")
    base = AutoModelForCausalLM.from_pretrained(
        args.base,
        attn_implementation="sdpa",
        low_cpu_mem_usage=True
    ).to(device=device, dtype=base_dtype)

    print(f"Attaching LoRA adapter from: {args.adapter}")
    model = PeftModel.from_pretrained(base, args.adapter)

    # If you trained in 4/8-bit you’d upcast here; (you trained plain LoRA so OK)
    if use_mps:
        model = model.to(dtype=torch.float16)

    print("Merging LoRA into base (this may take a bit)...")
    model = model.merge_and_unload()

    tok = AutoTokenizer.from_pretrained(args.base, use_fast=True)
    print(f"Saving merged model to: {args.out}")
    model.save_pretrained(args.out, safe_serialization=True)
    tok.save_pretrained(args.out)
    print("✅ Done.")

if __name__ == "__main__":
    main()
